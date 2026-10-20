<?php

declare(strict_types=1);

namespace Tests\Unit\src\Parser;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\FailureDiagnosticAtStart;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

abstract class ParserTest extends TestCase
{
    protected function oneByteParser(): Parser
    {
        return new class extends Parser {
            protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
            {
                return $this->success($source, $byteOffset, 1);
            }

            public function __toString(): string
            {
                return 'one byte';
            }
        };
    }
}

uses(ParserTest::class);

test('string and integer node names remain literal', function (string|int $name): void {
    $parser = (new Text('a'))->name($name);
    foreach ([$parser, $parser->freeze()] as $grammar) {
        $result = $grammar->parseAll(new ByteSource('a'));
        $this->assertTrue($result->success);
        $this->assertSame($name, $result->node->name);
        $this->assertSame($name, $result->ast()->name);
    }
})->with(['mail', 'key', 'strlen', 0, 42]);

test('dynamic node names run only during successful recognition', function (string $kind): void {
    $callback = new class {
        public int $calls = 0;

        public function __invoke(ConcreteNode $node): string
        {
            ++$this->calls;

            return 'token_' . $node->rawText();
        }
    };
    $name = match ($kind) {
        'closure' => $callback->__invoke(...),
        'array' => [$callback, '__invoke'],
        'object' => $callback,
    };
    $parser = (new Text('a'))->name($name);
    $frozen = $parser->freeze();
    $this->assertSame(0, $callback->calls);

    foreach ([$parser, $frozen] as $grammar) {
        $this->assertFalse($grammar->parseAll(new ByteSource('b'))->success);
        $calls = $callback->calls;
        $result = $grammar->parseAll(new ByteSource('a'));
        $this->assertTrue($result->success);
        $this->assertSame('token_a', $result->node->name);
        $this->assertSame('token_a', $result->ast()->name);
        $this->assertSame($calls + 1, $callback->calls);
    }
    $this->assertSame(2, $callback->calls);
})->with(['closure', 'array', 'object']);

test('plain preparation respects later metadata and frozen isolation', function (string $operation): void {
    $parser = new Text('a');
    $source = new ByteSource('a');
    $this->assertTrue($parser->parseAll($source)->success);
    $plain = $parser->freeze();
    $calls = 0;
    match ($operation) {
        'name' => $parser->name(static fn(): string => 'named', 'group', true),
        'tag' => $parser->tag('marked'),
        'guard' => $parser->guard(static fn(): bool => false),
        'validate' => $parser->validateSemantic(static fn(): string => 'invalid'),
        'mapValue' => $parser->mapValue(static fn(): string => 'mapped'),
        'map' => $parser->map(static fn(string $value): string => strtoupper($value)),
        'effect' => $parser->onCommit(static function () use (&$calls): void {
            ++$calls;
        }),
    };
    foreach ([$parser, $parser->freeze()] as $configured) {
        $result = $configured->parseAll($source);
        if ($operation === 'guard') {
            $this->assertFalse($result->success);
            continue;
        }
        $this->assertTrue($result->success);
        match ($operation) {
            'name' => $this->assertSame(
                ['named', 'group', true],
                [$result->node->name, $result->node->group, $result->node->semanticLeaf],
            ),
            'tag' => $this->assertSame(['marked'], $result->node->tags),
            'validate' => $this->assertFalse($result->validate()->valid),
            'mapValue' => $this->assertSame('mapped', $result->value()),
            'map' => $this->assertSame('A', $result->value()),
            'effect' => $result->commit(),
        };
    }
    $this->assertSame($operation === 'effect' ? 2 : 0, $calls);
    $result = $plain->parseAll($source);
    $this->assertTrue($result->success);
    $this->assertNull($result->node->name);
    $this->assertSame([], $result->node->tags);
    $this->assertSame('a', $result->value());
    $this->assertTrue($result->validate()->valid);
})->with(['name', 'tag', 'guard', 'validate', 'mapValue', 'map', 'effect']);

test('plain parsers preserve custom match preparation hooks', function (): void {
    $parser = new class extends Parser {
        public ?Success $match = null;

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            return $this->match = new class (1) extends Success {
                public int $retentions = 0;

                public function retainExecutionState(): void
                {
                    ++$this->retentions;
                    parent::retainExecutionState();
                }
            };
        }

        public function __toString(): string
        {
            return 'custom match';
        }
    };
    $result = $parser->parseAll(new ByteSource('a'));

    $this->assertTrue($result->success);
    $this->assertSame(1, $parser->match->retentions);
    $this->assertSame('a', $result->node->rawText());
});

test('a wrapper shares only the child interval while retaining independent values and effects', function (
    bool $arrayChild,
    int $parentLength,
): void {
    $commits = [];
    $child = (new Text("\u{e9}"))->mapValue(static fn(): string => 'child');
    $child->onCommit(static function () use (&$commits): void {
        $commits[] = 'child';
    });
    $parser = new class ($child, $arrayChild, $parentLength) extends Parser {
        public ?AbstractMatch $childMatch = null;
        private Parser $child;

        public function __construct(Parser $child, private bool $arrayChild, private int $parentLength)
        {
            $this->child = $child;
        }

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            $child = $this->child->recognizeInSession($source, $byteOffset, $session);
            assert($child instanceof Success);
            $this->childMatch = $child;

            return $this->success($source, $byteOffset, $this->parentLength, $this->arrayChild ? [$child] : $child);
        }

        public function __toString(): string
        {
            return 'wrapper';
        }
    };
    $parser->mapValue(static fn(): string => 'parent');
    $parser->onCommit(static function () use (&$commits): void {
        $commits[] = 'parent';
    });
    $source = new Utf8Source("x\u{e9}!");
    $result = $parser->matchPrefix($source, 1);
    $this->assertTrue($result->success);
    $this->assertSame($parentLength === 2 ? "\u{e9}" : "\u{e9}!", $result->rawText());
    $this->assertSame($parentLength === 2, $result->span === $parser->childMatch->span);
    $this->assertSame('parent', $result->value());
    $this->assertSame('child', $parser->childMatch->getValue());
    $this->assertSame([], $commits);
    $result->commit();
    $result->commit();
    $this->assertSame(['child', 'parent'], $commits);
})->with([false, true])->with([2, 3]);

test('plain delegated matches preserve child projections effects and session scopes', function (): void {
    $projections = 0;
    $commits = 0;
    $child = (new Text("\u{e9}"))->mapValue(static function () use (&$projections): string {
        ++$projections;

        return 'child value';
    })->onCommit(static function () use (&$commits): void {
        ++$commits;
    });
    $parser = new class ($child) extends Parser {
        public ?AbstractMatch $childMatch = null;
        private Parser $child;

        public function __construct(Parser $child)
        {
            $this->child = $child;
        }

        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            return $this->childMatch = $this->child->recognizeInSession($source, $byteOffset, $session);
        }

        public function __toString(): string
        {
            return 'delegate';
        }
    };
    $parser->label('letter')->diagnosticRule('delegate');
    $source = new Utf8Source("x\u{e9}");
    $session = new ParseSession($source);
    $session->recordFailure(0, 'earlier observation');
    $match = $parser->recognizeInSession($source, 1, $session);
    $result = new ParseResult($match);

    $this->assertNotSame($parser->childMatch, $match);
    $this->assertSame($parser->childMatch->node, $result->node->children[0]);
    $this->assertSame($parser->childMatch->span, $result->span);
    $this->assertSame([1, 3], [$result->start, $result->end]);
    $this->assertSame("\u{e9}", $result->rawText());
    $this->assertSame(['earlier observation'], $result->diagnostic->expected);
    $this->assertSame([], $session->rulePath());
    $this->assertSame([0, 0], [$projections, $commits]);
    $this->assertSame('child value', $result->value());
    $this->assertSame('child value', $parser->childMatch->getValue());
    $this->assertSame(1, $projections);
    $this->assertSame(0, $commits);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(1, $commits);
    $this->assertTrue($parser->recognizeInSession($source, 1, $session)->accepted);
    $this->assertSame([], $session->rulePath());
});

test('explicit diagnostic names take precedence after the class label has been used', function () {
    $parser = new Text('a');
    $this->assertSame('Text', $parser->graphLabel());
    $parser->diagnosticRule('letter');
    $this->assertSame('letter', $parser->graphLabel());
    $result = $parser->parseAll(new ByteSource('b'));
    $this->assertSame([['letter']], $result->diagnostic->rulePaths);
});

test('base runtime binds custom recognition to the source', function (): void {
    $parser = $this->oneByteParser();
    $source = new ByteSource('ab');
    $result = $parser->matchPrefix($source, 1);

    $this->assertTrue($result->success);
    $this->assertSame('b', $result->rawText());
    $this->assertSame($source, $result->span->source);
    $this->assertSame([1, 2], [$result->start, $result->end]);
});

test('default children are empty', function (): void {
    $this->assertSame([], $this->oneByteParser()->children());
});

test('partial utf8 recognition is rejected', function (): void {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Parser returned a partial UTF-8 code point');

    $this->oneByteParser()->matchPrefix(new Utf8Source("\u{e9}"));
});

test('marked parser reports entry offset without changing failure progress', function (): void {
    $parser = new class extends Parser implements FailureDiagnosticAtStart {
        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            return $this->failure($source, $byteOffset, 2);
        }

        public function __toString(): string
        {
            return 'predicate';
        }
    };
    $source = new ByteSource('xab');
    $session = new ParseSession($source);
    $match = $parser->recognizeInSession($source, 1, $session);

    $this->assertFalse($match->accepted);
    $this->assertSame(2, $match->length);
    $this->assertSame(1, $match->farthestFailure);
    $this->assertSame(1, $match->diagnostic->byteOffset);
});

test('unmarked parser reports observed failure progress', function (): void {
    $parser = new class extends Parser {
        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            return $this->failure($source, $byteOffset, 2);
        }

        public function __toString(): string
        {
            return 'consuming parser';
        }
    };
    $result = $parser->matchPrefix(new ByteSource('xab'), 1);

    $this->assertFalse($result->success);
    $this->assertSame(3, $result->diagnostic->byteOffset);
});

test('marker preserves explicit diagnostics', function (): void {
    $parser = new class extends Parser implements FailureDiagnosticAtStart {
        protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
        {
            $session->recordFailure($byteOffset + 1, 'explicit expectation');

            return $this->failure($source, $byteOffset, 2);
        }

        public function __toString(): string
        {
            return 'predicate';
        }
    };
    $result = $parser->matchPrefix(new ByteSource('xab'), 1);

    $this->assertSame(2, $result->diagnostic->byteOffset);
    $this->assertSame(['explicit expectation'], $result->diagnostic->expected);
});
