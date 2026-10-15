<?php

declare(strict_types=1);

namespace Tests\features\parsing;

use Vanderlee\Comprehend\Parser\Structure\All;
use LogicException;
use RuntimeException;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\Utf8Source;

class CanonicalArchitectureTest extends ParserTestCase
{
    public function testCustomParserUsesTypedSourceUnitsWithoutOptIn(): void
    {
        $parser = new class extends Parser {
            protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
            {
                $first = $source->readUnit($offset);
                $second = $first === null ? null : $source->readUnit($first->end);

                return $second === null ? $this->failure(
                    $source,
                    $offset,
                    $first?->byteLength() ?? 0,
                ) : $this->success($source, $offset, $second->end - $offset);
            }

            public function __toString(): string
            {
                return 'two units';
            }
        };
        $text = $parser->parseAll(new Utf8Source("é😀"));
        $this->assertResult(true, 6, $text);
        $this->assertSame('é😀', $text->node->rawText());
        $this->assertResult(true, 2, $parser->parseAll(new ByteSource("\xff\x00")));
        $failure = $parser->parseAll(new Utf8Source('é'));
        $this->assertResult(false, 0, $failure);
        $this->assertSame(2, $failure->farthestFailure);
    }

    public function testSourceInterfaceWorksWithoutConcreteClassDispatch(): void
    {
        $source = new class implements Source {
            private Utf8Source $inner;

            public function __construct()
            {
                $this->inner = new Utf8Source('é');
            }

            public function mode(): SourceMode
            {
                return SourceMode::UTF8;
            }

            public function byteLength(): int
            {
                return $this->inner->byteLength();
            }

            public function sliceBytes(int $start, int $end): string
            {
                return $this->inner->sliceBytes($start, $end);
            }

            public function span(int $start, int $end): Span
            {
                return new Span($this, $start, $end);
            }

            public function isUnitBoundary(int $offset): bool
            {
                return $this->inner->isUnitBoundary($offset);
            }

            public function readUnit(int $offset): ?Span
            {
                $span = $this->inner->readUnit($offset);

                return $span === null ? null : $this->span($span->start, $span->end);
            }
        };
        $result = (new Text('É'))->caseInsensitive()->parseAll($source);
        $this->assertResult(true, 2, $result);
        $this->assertSame($source, $result->span->source);
        $this->assertSame('é', $result->rawText());
    }

    public function testPartialCodePointReturnedByCustomParserIsRejected(): void
    {
        $parser = new class extends Parser {
            protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
            {
                return $this->success($source, $offset, 1);
            }

            public function __toString(): string
            {
                return 'broken';
            }
        };
        $this->expectException(LogicException::class);
        $parser->parseAll(new Utf8Source('é'));
    }

    public function testForeignSourceReturnedByCustomParserIsRejected(): void
    {
        $parser = new class extends Parser {
            protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
            {
                return $this->success(new ByteSource('a'), $offset, 1);
            }

            public function __toString(): string
            {
                return 'broken';
            }
        };
        $this->expectExceptionMessage('foreign source');
        $parser->parseAll(new ByteSource('a'));
    }

    public function testSessionCannotRecognizeAnotherSource(): void
    {
        $source = new ByteSource('a');
        $this->expectExceptionMessage('session source');
        (new Text('a'))->recognizeInSession(new ByteSource('a'), 0, new ParseSession($source));
    }

    /** @dataProvider coercionCalls */
    public function testImplicitArgumentCoercionIsRemoved(callable $call): void
    {
        $this->expectException(\TypeError::class);
        $call();
    }

    public function coercionCalls(): array
    {
        return [
            [static fn() => new Sequence('a')],
            [static fn() => Choice::first(['a', 'b'])],
            [static fn() => new Repeat(97)],
            [static fn() => (new Sequence(new Text('a')))->spacing(true)],
            [static fn() => (new Text('a'))->parseAll('a')],
            [static fn() => (new Ruleset())->define('letter', 'a')],
        ];
    }

    public function testRuleFactoriesAndForwardReferencesUseExplicitCalls(): void
    {
        $rules = new Ruleset([], 'test');
        $reference = $rules->ref('pair', new Text('é'));
        $rules->define('pair', static fn(Parser $item): Parser => new Sequence($item, $item));
        $result = $reference->parseAll(new Utf8Source('éé'));
        $this->assertResult(true, 4, $result);
        $this->assertSame('pair', $result->ast()->name);
        $this->assertSame('test', $result->ast()->group);
        $definition = new Definition(static fn(string $literal): Parser => new Text($literal));
        $rules->define('item', $definition);
        $this->assertTrue($rules->ref('item', 'x')->parseAll(new ByteSource('x'))->success);
    }

    public function testUndefinedReferenceDoesNotGuessALiteral(): void
    {
        $rules = new Ruleset();
        $reference = $rules->ref('missing');
        $this->assertFalse($rules->defined('missing'));
        $this->expectExceptionMessage('Undefined grammar rule: missing');
        $reference->parseAll(new ByteSource('missing'));
    }

    public function testFactoryMustReturnAParser(): void
    {
        $rules = new Ruleset(['bad' => static fn() => 'text']);
        $this->expectExceptionMessage('Rule factory must return Comprehend or Parser: bad');
        $rules->ref('bad')->parseAll(new ByteSource('text'));
    }

    public function testDefinitionFactoryMustReturnAParser(): void
    {
        $definition = new Definition(static fn() => 'text');
        $this->expectExceptionMessage('Definition factory must return Comprehend or Parser');
        $definition->build()->parseAll(new ByteSource('text'));
    }

    public function testGrammarNamesAndRedefinitionAreIsolated(): void
    {
        $first = new Ruleset(['letter' => new Text('a')], 'first');
        $second = new Ruleset(['letter' => new Text('b')], 'second');
        $reference = $first->ref('letter');
        $old = $reference->parseAll(new ByteSource('a'));
        $first->define('letter', new Text('c'));
        $this->assertSame('a', $old->rawText());
        $this->assertSame('first', $old->ast()->group);
        $this->assertTrue($reference->parseAll(new ByteSource('c'))->success);
        $this->assertSame('second', $second->ref('letter')->parseAll(new ByteSource('b'))->ast()->group);
        $first->undefine('letter');
        $this->assertFalse($first->defined('letter'));
        $this->expectExceptionMessage('Undefined grammar rule: letter');
        $reference->parseAll(new ByteSource('c'));
    }

    public function testDefinitionValidationIsSemanticAndDoesNotRetryChoice(): void
    {
        $events = [];
        $definition = new Definition(
            new Text('a'),
            static function (ParseResult $result) use (&$events): string {
                $events[] = $result->rawText();

                return 'rejected';
            },
        );
        $parser = Choice::first($definition->implementation()->name('first'), (new Text('a'))->name('second'));
        $result = $parser->parseAll(new ByteSource('a'));
        $this->assertSame([], $events);
        $this->assertSame('first', $result->ast()->children[0]->name);
        $this->assertFalse($result->commit());
        $this->assertFalse($result->commit());
        $this->assertSame(['a'], $events);
    }

    public function testExplicitMappingOverridesInheritedDefinitionValue(): void
    {
        $definition = new Definition((new Text('a'))->mapValue(static fn() => 42));
        $inherited = $definition->build()->parseAll(new ByteSource('a'));
        $overridden = $definition->build()->mapValue(static fn() => 99)->parseAll(new ByteSource('a'));
        $this->assertSame(42, $inherited->value());
        $this->assertSame(99, $overridden->value());
    }

    public function testLocalAndParentResultReadsEvaluateTransformsAndProcessorsOnce(): void
    {
        $maps = $processors = 0;
        $child = (new Text('a'))->setResult(
            'item',
            static function () use (&$maps): int {
                return ++$maps;
            },
        );
        $child->validateSemantic(static fn(ParseResult $result): bool => $result->result('item') === 1);
        $definition = new Definition($child);
        $definition->addProcessor(
            'total',
            static function (array $local) use (&$processors): int {
                ++$processors;

                return $local['item'] + 1;
            },
        );
        $scope = $definition->implementation()->validateSemantic(
            static fn(ParseResult $result): bool => $result->result('total') === 2,
        );
        $result = (new Sequence($scope, new Text('b')))->parseAll(new ByteSource('ab'));
        $this->assertTrue($result->validate()->valid);
        $this->assertSame(['total' => 2], $result->results());
        $this->assertSame(['total' => 2], $result->results());
        $this->assertSame([1, 1], [$maps, $processors]);
    }

    public function testNamedResultErrorsAreRetained(): void
    {
        $calls = 0;
        $failure = new RuntimeException('collection failed');
        $result = (new Text('a'))->setResult(
            'value',
            static function () use (&$calls, $failure) {
                ++$calls;
                throw $failure;
            },
        )->parseAll(new ByteSource('a'));
        for ($i = 0; $i < 2; ++$i) {
            try {
                $result->results();
                $this->fail('Expected retained error');
            } catch (RuntimeException $error) {
                $this->assertSame($failure, $error);
            }
        }
        $this->assertSame(1, $calls);
    }

    public function testStoredNullCollectionValueIsDistinctFromMissingValue(): void
    {
        $result = (new Text('a'))->setResult('nullable', static fn() => null)->parseAll(new ByteSource('a'));
        $this->assertSame(['nullable' => null], $result->results());
        $this->assertNull($result->result('nullable', 'fallback'));
        $this->assertSame('fallback', $result->result('missing', 'fallback'));
    }

    public function testDirectivesPreserveChildCacheAndHaveTheirOwnEffects(): void
    {
        $events = [];
        $child = (new Text('a'))->mapValue(static fn() => 42)->onCommit(
            static function () use (&$events) {
                $events[] = 'child';
            },
        );
        $wrapper = (new CaseSensitive(false, $child))->name('wrapper')->onCommit(
            static function (ParseResult $result) use (&$events) {
                $events[] = $result->rawText();
            },
        );
        $result = $wrapper->parseAll(new ByteSource('A'));
        $this->assertSame(42, $result->value());
        $this->assertSame([], $events);
        $this->assertTrue($result->commit());
        $this->assertTrue($result->commit());
        $this->assertSame(['child', 'A'], $events);
    }

    public function testTriviaUsesSessionCasePolicyAndRestoresScopesAfterExceptions(): void
    {
        $trivia = new Text('X');
        $grammar = new CaseSensitive(false, (new Sequence(new Text('a'), new Text('b')))->spacing($trivia));
        $this->assertTrue($grammar->parseAll(new ByteSource('axb'))->success);
        $source = new ByteSource('ab');
        $session = new ParseSession($source);
        $thrower = new class extends Parser {
            protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
            {
                throw new RuntimeException('scope');
            }

            public function __toString(): string
            {
                return 'thrower';
            }
        };
        $scoped = new Prefer(Prefer::LONGEST, new CaseSensitive(false, new Space(new Text(' '), $thrower->lexeme())));
        try {
            $scoped->recognizeInSession($source, 0, $session);
            $this->fail('Expected exception');
        } catch (RuntimeException $error) {
            $this->assertSame('scope', $error->getMessage());
        }
        $this->assertTrue($session->isCaseSensitive());
        $this->assertSame(Prefer::FIRST, $session->getPreference());
        $this->assertFalse($session->isLexeme());
        $this->assertSame(0, $session->skipSpacing($source, 0));
        $this->assertSame([], $session->diagnosticState()[2]);
    }

    public function testInvalidTriviaValidationIsReportedOnce(): void
    {
        $trivia = (new Text(' '))->validateSemantic(static fn() => 'invalid trivia');
        $result = (new Sequence(new Text('a'), new Text('b')))->spacing($trivia)->parseAll(new ByteSource('a b'));
        $this->assertSame(['invalid trivia'], $result->validate()->errors);
        $this->assertFalse($result->commit());
    }

    public function testInterveningTriviaIncludesZeroWidthGrammarItems(): void
    {
        $parser = (new Sequence(new Nothing(), new Text('a')))->spacing(new Text(' '));
        $result = $parser->parseAll(new ByteSource(' a'));
        $this->assertTrue($result->success);
        $this->assertSame(' a', $result->rawText());
        $this->assertTrue($result->node->children[1]->isTrivia);
    }

    public function testRecursiveStubUsesExplicitSetter(): void
    {
        $stub = new Stub();
        $stub->setParser(Choice::first(new Text('x'), new Sequence(new Text('('), $stub, new Text(')'))));
        $this->assertTrue($stub->parseAll(new ByteSource('((x))'))->success);
        $this->assertInstanceOf(Choice::class, $stub->getParser());
    }

    public function testChildEffectCannotRecursivelyCommitItsOwnResult(): void
    {
        $calls = 0;
        $child = (new Text('a'))->onCommit(
            static function (ParseResult $result) use (&$calls) {
                ++$calls;
                $result->commit();
            },
        );
        $result = (new Sequence($child, new Text('b')))->parseAll(new ByteSource('ab'));
        for ($i = 0; $i < 2; ++$i) {
            try {
                $result->commit();
                $this->fail('Expected recursive commit failure');
            } catch (LogicException $error) {
                $this->assertSame('Recursive effect commit', $error->getMessage());
            }
        }
        $this->assertSame(1, $calls);
        $this->assertFalse($result->isCommitted());
    }

    public function testSelectedChildResultReportsCommittedAfterParentCommit(): void
    {
        $childResult = null;
        $child = (new Text('a'))->onCommit(
            static function (ParseResult $result) use (&$childResult) {
                $childResult = $result;
            },
        );
        $result = (new Sequence($child, new Text('b')))->parseAll(new ByteSource('ab'));
        $this->assertTrue($result->commit());
        $this->assertTrue($childResult->isCommitted());
        $this->assertTrue($childResult->commit());
    }

    public function testIntersectionRetainsAllSelectedCollectionsAndEffects(): void
    {
        $events = [];
        $short = (new Text('a'))->setResult('short')->onCommit(
            static function (ParseResult $result) use (&$events) {
                $events[] = $result->rawText();
            },
        );
        $long = (new Text('ab'))->setResult('long')->onCommit(
            static function (ParseResult $result) use (&$events) {
                $events[] = $result->rawText();
            },
        );
        $result = (new All($short, $long))->matchPrefix(new ByteSource('ab'));
        $this->assertSame(1, $result->consumedByteLength);
        $this->assertSame(['short' => 'a', 'long' => 'ab'], $result->results());
        $this->assertSame([], $events);
        $this->assertTrue($result->commit());
        $this->assertSame(['a', 'ab'], $events);
    }
}
