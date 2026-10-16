<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

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
