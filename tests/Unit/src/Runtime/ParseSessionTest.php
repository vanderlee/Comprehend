<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use LogicException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use TypeError;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(TestCase::class);

test('prepared spacing syntax stays source bound while skipSpacing retains independent records', function (): void {
    $grammar = (new ReadWhileSet(' '))->freeze();
    $spacing = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $source = new Utf8Source('  a');
    $session = new ParseSession($source, $spacing);
    $this->assertSame(2, $session->skipSpacingSyntax($source, 0, $syntax));
    $this->assertInstanceOf(ConcreteNode::class, $syntax);
    $this->assertTrue($syntax->isTrivia);
    $this->assertSame($source, $syntax->span->source);
    $syntaxWork = $session->workUsed();
    $this->assertSame(2, $session->skipSpacing($source, 0, $match));
    $this->assertSame($syntaxWork, $session->workUsed() - $syntaxWork);
    $this->assertInstanceOf(Success::class, $match);
    $this->assertEquals($syntax, $match->node);
    $this->assertSame(0, $session->skipSpacingSyntax($source, 2, $empty));
    $this->assertInstanceOf(ConcreteNode::class, $empty);
    $this->assertSame('', $empty->rawText());
    $this->assertSame(1, $session->skipSpacingSyntax($source, 1, $second));
    $this->assertNotSame($syntax, $second);
    $this->assertSame('  ', $syntax->rawText());
    $ordinary = (new GraphSnapshot())->copy($spacing);
    $ordinarySession = new ParseSession($source, $ordinary);
    $this->assertSame(2, $ordinarySession->skipSpacingSyntax($source, 0, $ordinarySyntax));
    $this->assertInstanceOf(Success::class, $ordinarySyntax);
});

test('required spacing and inactive policies retain syntax failure and zero width contracts', function (): void {
    $grammar = (new ReadWhileSet(' ', min: 1))->freeze();
    $spacing = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $source = new ByteSource('a');
    $session = new ParseSession($source, $spacing);
    $this->assertFalse($session->skipSpacingSyntax($source, 0, $syntax));
    $this->assertNull($syntax);
    $this->assertNotNull($session->diagnostic());
    $this->assertFalse($session->isLexeme());
    $session->pushSpacer(null);
    $this->assertSame(0, $session->skipSpacingSyntax($source, 0, $syntax));
    $this->assertNull($syntax);
    $session->popSpacer();
    $session->pushLexeme();
    $this->assertSame(0, $session->skipSpacingSyntax($source, 0, $syntax));
    $this->assertNull($syntax);
    $session->popLexeme();
});

test('syntax only trivia rejects foreign sources and partial UTF8 offsets and restores scope', function (): void {
    $grammar = (new ReadWhileSet(' '))->freeze();
    $spacing = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $source = new Utf8Source("\u{e9} ");
    $session = new ParseSession($source, $spacing);
    foreach ([[new ByteSource(' '), 0], [$source, -1], [$source, 4], [$source, 1]] as [$input, $offset]) {
        try {
            $session->skipSpacingSyntax($input, $offset, $syntax);
            $this->fail('Invalid source position must be rejected');
        } catch (InvalidArgumentException) {
            $this->assertNull($syntax);
            $this->assertFalse($session->isLexeme());
        }
    }
    $this->assertSame(1, $session->skipSpacingSyntax($source, 2, $syntax));
    $this->assertSame(' ', $syntax->rawText());
});

test('spacing reuses its wrapper but retains independent matches and effects', function (): void {
    $source = new ByteSource('  ');
    $trivia = new class (' ') extends Text {
        public int $wrappers = 0;

        public function trivia(): Trivia
        {
            ++$this->wrappers;

            return parent::trivia();
        }
    };
    $effects = [];
    $trivia->onCommit(static function (ParseResult $result) use (&$effects): void {
        $effects[] = $result->span->start;
    });
    $session = new ParseSession($source, $trivia);
    $this->assertSame(1, $session->skipSpacing($source, 0, $first));
    $session->pushSpacer(null);
    $this->assertSame(0, $session->skipSpacing($source, 1));
    $session->popSpacer();
    $session->pushSpacer($trivia);
    $this->assertSame(1, $session->skipSpacing($source, 1, $second));
    $session->popSpacer();

    $this->assertSame(1, $trivia->wrappers);
    $this->assertNotSame($first, $second);
    $this->assertTrue($first->node->isTrivia);
    $this->assertTrue($second->node->isTrivia);
    $this->assertSame([], $effects);
    $second->commit();
    $first->commit();
    $second->commit();
    $this->assertSame([1, 0], $effects);

    $otherSession = new ParseSession($source, $trivia);
    $this->assertSame(1, $otherSession->skipSpacing($source, 0));
    $this->assertSame(2, $trivia->wrappers);
});

test('unused spacing under lexeme does not create wrappers', function (): void {
    $source = new ByteSource(' ');
    $trivia = new class (' ') extends Text {
        public function trivia(): Trivia
        {
            throw new LogicException('Unused trivia must not be wrapped');
        }
    };
    $session = new ParseSession($source, $trivia);
    $session->pushLexeme();
    $this->assertSame(0, $session->skipSpacing($source, 0));
    $session->popLexeme();
    $this->assertFalse($session->isLexeme());
});

test('nested spacing policies keep their own wrappers', function (): void {
    $source = new ByteSource(' - ');
    $session = new ParseSession($source, new Text(' '));
    $this->assertSame(1, $session->skipSpacing($source, 0, $first));
    $session->pushSpacer(new Text('-'));
    $this->assertSame(1, $session->skipSpacing($source, 1, $middle));
    $session->popSpacer();
    $this->assertSame(1, $session->skipSpacing($source, 2, $last));

    $this->assertSame(' ', $first->node->rawText());
    $this->assertSame('-', $middle->node->rawText());
    $this->assertSame(' ', $last->node->rawText());
});

test('unchanged diagnostic snapshots are shared without mutating retained failures', function (): void {
    $session = new ParseSession(new ByteSource('abc'));
    $session->recordFailure(1, 'first', [['rule']]);
    $first = $session->diagnostic();
    $this->assertSame($first, $session->diagnostic());
    $session->recordFailure(0, 'nearer', [['ignored']]);
    $this->assertSame($first, $session->diagnostic());
    $session->recordFailure(1, 'second', [['other']]);
    $second = $session->diagnostic();
    $this->assertNotSame($first, $second);
    $this->assertSame(['first'], $first->expected);
    $this->assertSame([['rule']], $first->rulePaths);
    $this->assertSame(['first', 'second'], $second->expected);
    $this->assertSame($second, $session->diagnostic());
});

test('observed failure offsets include deferred lists without materializing their union', function (): void {
    $session = new ParseSession(new ByteSource('abc'));
    $checkpoint = $session->diagnosticState();
    $session->recordFailure(2, 'item');
    $session->deferListDiagnostics($checkpoint);
    $session->recordFailure(1, 'close');
    $this->assertSame(1, $session->farthestFailure());
    $this->assertSame(2, $session->farthestFailure(true));
    $this->assertSame(2, $session->diagnostic(true)->byteOffset);
    $session->recordFailure(3, 'farther');
    $this->assertSame(3, $session->farthestFailure(true));
    $session->restoreDiagnostics($checkpoint);
    $this->assertNull($session->farthestFailure(true));
});

test('restored diagnostic revisions cannot reuse a different speculative failure', function (): void {
    $session = new ParseSession(new ByteSource('abc'));
    $session->recordFailure(0, 'base');
    $checkpoint = $session->diagnosticState();
    $session->recordFailure(2, 'abandoned', [['first branch']]);
    $abandoned = $session->diagnostic();
    $revision = $session->failureRevision();
    $session->restoreDiagnostics($checkpoint);
    $session->recordFailure(2, 'selected', [['second branch']]);
    $selected = $session->diagnostic();
    $this->assertSame($revision, $session->failureRevision());
    $this->assertNotSame($abandoned, $selected);
    $this->assertSame(['abandoned'], $abandoned->expected);
    $this->assertSame(['selected'], $selected->expected);
    $this->assertSame([['second branch']], $selected->rulePaths);
});

test('deferred list diagnostic merges are cached and isolated by rollback', function (): void {
    $session = new ParseSession(new ByteSource('abc'));
    $checkpoint = $session->diagnosticState();
    $session->recordFailure(1, 'list', [['list']]);
    $session->deferListDiagnostics($checkpoint);
    $deferred = $session->diagnostic(true);
    $this->assertNull($session->diagnostic());
    $this->assertSame($deferred, $session->diagnostic(true));
    $session->recordFailure(1, 'outer', [['outer']]);
    $combined = $session->diagnostic(true);
    $this->assertSame(['outer', 'list'], $combined->expected);
    $this->assertSame([['outer'], ['list']], $combined->rulePaths);
    $this->assertSame($combined, $session->diagnostic(true));
    $this->assertSame(['list'], $deferred->expected);
    $session->restoreDiagnostics($checkpoint);
    $this->assertNull($session->diagnostic(true));
});

test('regex subject ownership is local to each session', function () {
    $first = new ParseSession(new ByteSource('first'));
    $second = new ParseSession(new ByteSource('second'));
    $this->assertSame('first', $first->regexSubject());
    $this->assertSame('second', $second->regexSubject());
    $this->assertSame('first', $first->regexSubject());
});

test('batched literal work preserves every exhausted unit position and partial count', function (): void {
    foreach ([new ByteSource('é🙂z'), new Utf8Source('é🙂z')] as $source) {
        $positions = $source instanceof ByteSource ? range(0, 6) : [0, 2, 6];
        foreach ($positions as $used => $position) {
            $session = new ParseSession($source, limits: new ParseLimits(maxWork: $used + 1));
            $session->consumeWork();
            try {
                $session->consumeLiteralWork('é🙂z', count($positions), 0);
                $this->fail('Expected exhausted literal work');
            } catch (ParseLimitException $error) {
                $this->assertSame($position, $error->byteOffset);
                $this->assertSame($used + 1, $session->workUsed());
            }
        }
        $session = new ParseSession($source, limits: new ParseLimits(maxWork: count($positions)));
        $session->consumeLiteralWork('é🙂z', count($positions), 0);
        $this->assertSame(count($positions), $session->workUsed());
    }
});

test('throwing trivia clears the output match and restores lexeme scope', function (): void {
    $source = new ByteSource(' ');
    $failure = new RuntimeException('Trivia guard failed');
    $trivia = (new Text(' '))->guard(static function () use ($failure): never {
        throw $failure;
    });
    $session = new ParseSession($source, $trivia);
    $match = new Success();

    try {
        $session->skipSpacing($source, 0, $match);
        $this->fail('Expected trivia recognition to throw');
    } catch (RuntimeException $error) {
        $this->assertSame($failure, $error);
    }

    $this->assertNull($match);
    $this->assertFalse($session->isLexeme());
    $session->pushSpacer(new Text(' '));
    $this->assertSame(1, $session->skipSpacing($source, 0, $match));
    $this->assertSame(' ', $match->node->rawText());
    $this->assertTrue($match->node->isTrivia);
});

test('invalid choice policy does not change the active scope', function (): void {
    $session = new ParseSession(new ByteSource(''));
    $session->pushPreference(ChoicePolicy::LONGEST);

    try {
        $session->pushPreference('invalid');
        $this->fail('Expected an invalid choice policy');
    } catch (TypeError $error) {
        $this->assertStringContainsString(ChoicePolicy::class, $error->getMessage());
    }

    $this->assertSame(ChoicePolicy::LONGEST, $session->getPreference());
    $session->popPreference();
    $this->assertSame(ChoicePolicy::FIRST, $session->getPreference());
});

test('popping an exhausted case scope is rejected', function (): void {
    $session = new ParseSession(new ByteSource(''));
    $this->assertTrue($session->popCaseSensitivity());
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Case sensitivity scope is empty');

    $session->popCaseSensitivity();
});

test('reading an exhausted preference scope is rejected', function (): void {
    $session = new ParseSession(new ByteSource(''));
    $session->popPreference();
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Choice preference scope is empty');

    $session->getPreference();
});

test('diagnostics merge ties and can be restored after a probe', function (): void {
    $session = new ParseSession(new ByteSource('abc'));
    $session->pushRule('root');
    $session->recordFailure(1, 'a');
    $session->recordFailure(1, 'b');
    $saved = $session->diagnosticState();
    $session->recordFailure(2, 'c');
    $session->restoreDiagnostics($saved);
    $session->popRule();

    $this->assertSame(1, $session->farthestFailure());
    $this->assertSame(['a', 'b'], $session->diagnostic()->expected);
    $this->assertSame([['root']], $session->diagnostic()->rulePaths);
    $this->assertSame(2, $session->failureRevision());
});

test('deferred list failures merge ties only on request and follow diagnostic checkpoints', function (): void {
    $session = new ParseSession(new ByteSource('abc'));
    $checkpoint = $session->diagnosticState();
    $session->pushRule('item');
    $session->recordFailure(2, 'item');
    $session->popRule();
    $session->deferListDiagnostics($checkpoint);
    $this->assertNull($session->diagnostic());
    $this->assertNull($session->farthestFailure());
    $session->pushRule('close');
    $session->recordFailure(2, 'close');
    $session->popRule();
    $failure = $session->diagnostic(true);
    $this->assertSame(['close', 'item'], $failure->expected);
    $this->assertSame([['close'], ['item']], $failure->rulePaths);
    $session->recordFailure(3, 'farther');
    $this->assertSame(['farther'], $session->diagnostic(true)->expected);
    $session->restoreDiagnostics($checkpoint);
    $this->assertNull($session->diagnostic(true));
});

test('failed work charge does not consume the remaining budget', function (): void {
    $session = new ParseSession(new ByteSource('a'), limits: new ParseLimits(maxWork: 2));
    $session->consumeWork(1);

    try {
        $session->consumeWork(2, 1);
        $this->fail('Expected work exhaustion');
    } catch (ParseLimitException $error) {
        $this->assertSame('work', $error->limit);
        $this->assertSame(1, $error->byteOffset);
    }

    $this->assertSame(1, $session->workUsed());
    $this->assertSame('a', $session->readSourceUnit(0)->rawText());
    $this->assertSame(2, $session->workUsed());
});

test('nearer failure advances revision without replacing farthest details', function (): void {
    $session = new ParseSession(new ByteSource('abc'));
    $session->recordFailure(2, 'farther', [['root', 'farther']]);
    $session->recordFailure(1, 'nearer', [['root', 'nearer']]);

    $this->assertSame(2, $session->failureRevision());
    $this->assertSame(2, $session->farthestFailure());
    $this->assertSame(['farther'], $session->diagnostic()->expected);
    $this->assertSame([['root', 'farther']], $session->diagnostic()->rulePaths);
});

test('rejected entry does not retain parser or depth state', function (): void {
    $session = new ParseSession(new ByteSource('a'), limits: new ParseLimits(maxDepth: 1));
    $outer = new Text('a');
    $inner = new Text('a');
    $session->enterParser($outer, 0);

    try {
        $session->enterParser($inner, 0);
        $this->fail('Expected depth exhaustion');
    } catch (ParseLimitException $error) {
        $this->assertSame('depth', $error->limit);
    }

    $session->leaveParser($outer, 0);
    $session->enterParser($inner, 0);
    $session->leaveParser($inner, 0);

    $this->assertSame(2, $session->workUsed());
});

test('recursion protection distinguishes positions and releases completed frames', function () {
    $session = new ParseSession(new ByteSource('ab'));
    $parser = new Text('a');
    $session->enterParser($parser, 0);
    $session->enterParser($parser, 1);
    try {
        $session->enterParser($parser, 0);
        $this->fail('Expected left recursion');
    } catch (LogicException $error) {
        $this->assertStringContainsString('Left recursion at byte 0', $error->getMessage());
    }
    $session->leaveParser($parser, 1);
    $session->leaveParser($parser, 0);
    $session->enterParser($parser, 0);
    $session->leaveParser($parser, 0);
    $this->assertSame(3, $session->workUsed());
});

test('failure detail membership preserves strict byte strings path boundaries and insertion order', function (): void {
    $session = new ParseSession(new ByteSource('a'));
    $expected = ['', '0', '00', "a\0b", "\xff"];
    $paths = [[], ['a', 'b'], ["a\0b"], ['0'], ['00'], ["\xff"]];
    foreach ($expected as $expectation) {
        $session->recordFailure(1, $expectation, $paths);
    }
    $diagnostic = $session->diagnostic();
    foreach (array_reverse($expected) as $expectation) {
        $session->recordFailure(1, $expectation, array_reverse($paths));
    }

    $this->assertSame($expected, $diagnostic->expected);
    $this->assertSame($paths, $diagnostic->rulePaths);
    $this->assertSame($diagnostic, $session->diagnostic());
});

test('restored failure details admit discarded entries again without duplicating retained entries', function (): void {
    $session = new ParseSession(new ByteSource('ab'));
    $session->recordFailure(1, 'first', [['root', 'first']]);
    $checkpoint = $session->diagnosticState();
    $original = $session->diagnostic();
    for ($pass = 0; $pass < 2; ++$pass) {
        $session->recordFailure(1, 'second', [['root', 'second']]);
        $session->recordFailure(2, 'farther', [['root', 'farther']]);
        $session->restoreDiagnostics($checkpoint);
        $session->recordFailure(1, 'first', [['root', 'first']]);
        $session->recordFailure(1, 'second', [['root', 'second']]);
        $this->assertSame(['first', 'second'], $session->diagnostic()->expected);
        $this->assertSame([['root', 'first'], ['root', 'second']], $session->diagnostic()->rulePaths);
        $session->restoreDiagnostics($checkpoint);
    }
    $this->assertSame(['first'], $original->expected);
    $this->assertSame($original->expected, $session->diagnostic()->expected);
    $this->assertSame($original->rulePaths, $session->diagnostic()->rulePaths);
});
