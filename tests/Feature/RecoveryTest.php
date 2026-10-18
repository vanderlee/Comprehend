<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Recovery\Recovery as RecoveryExtension;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverUntil;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\Recovery;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;

beforeEach(function (): void {
    $this->syntax = C::syntax()->extend(new RecoveryExtension());
});

test('recovery is explicit and valid operands preserve lazy values and effects', function () {
    $calls = 0;
    $effects = 0;
    $valid = $this->syntax->text('ok;')->mapValue(function () use (&$calls) {
        ++$calls;
        return 42;
    })->onCommit(function () use (&$effects) {
        ++$effects;
    });
    $result = $valid->recoverUntil(';', consumeBoundary: true)->parse('ok;');
    expect($result->success)->toBeTrue();
    expect($result->hasRecovery())->toBeFalse();
    expect($result->diagnostics())->toBe([]);
    expect($calls)->toBe(0);
    expect($result->value())->toBe(42);
    expect($result->value())->toBe(42);
    expect($calls)->toBe(1);
    expect($result->commit())->toBeTrue();
    expect($effects)->toBe(1);
    expect($this->syntax->text('ok;')->parse('bad;')->success)->toBeFalse();
});

test('recovery preserves error regions and blocks validation callbacks and all commit effects', function () {
    $effects = 0;
    $validations = 0;
    $statement = $this->syntax->text('ok;')->onCommit(function () use (&$effects) {
        ++$effects;
    });
    $grammar = $statement->recoverUntil(';', consumeBoundary: true)->some()
        ->validateSemantic(function () use (&$validations) {
            ++$validations;
            return true;
        })
        ->onCommit(function () use (&$effects) {
            ++$effects;
        });
    $result = $grammar->parse('ok;bad;wrong;ok;');
    expect($result->success)->toBeTrue();
    expect($result->hasRecovery())->toBeTrue();
    expect($result->rawText())->toBe('ok;bad;wrong;ok;');
    expect(array_map(fn(Recovery $r) => $r->span->rawText(), $result->recoveries()))->toBe(['bad;', 'wrong;']);
    expect(array_map(fn($d) => $d->byteOffset, $result->diagnostics()))->toBe([3, 7]);
    expect($result->validate()->valid)->toBeFalse();
    expect(array_column($result->validate()->issues, 'code'))->toBe(['syntax.recovered', 'syntax.recovered']);
    expect(array_map(fn($issue) => $issue->span->rawText(), $result->validate()->issues))
        ->toBe(['bad;', 'wrong;']);
    expect($result->commit())->toBeFalse();
    expect($result->commit())->toBeFalse();
    expect($effects)->toBe(0);
    expect($validations)->toBe(0);
    $error = $result->node->children[1]->children[0];
    expect($error->tags)->toBe(['error', 'recovered']);
    expect($error->span->rawText())->toBe('bad;');
});

test('a recovered value is an explicit record rather than a fabricated operand value', function () {
    $operand = $this->syntax->text('ok')->mapValue(fn() => throw new LogicException('invalid value'));
    $result = $this->syntax->recoverUntil($operand, ';', true)->parse('bad;');
    expect($result->value())->toBeInstanceOf(Recovery::class);
    expect($result->value())->toBe($result->recoveries()[0]);
    expect($result->value()->span->rawText())->toBe('bad;');
});

test('boundary probes do not retain values effects validation or diagnostics', function () {
    $boundary = $this->syntax->text(';')->mapValue(fn() => throw new LogicException('probe value'))
        ->validateSemantic(fn() => throw new LogicException('probe validation'))
        ->onCommit(fn() => throw new LogicException('probe effect'));
    $result = $this->syntax->recoverUntil('ok', $boundary, true)->then('end')->parse('bad;end');
    expect($result->success)->toBeTrue();
    expect($result->diagnostics()[0]->expected)->toBe(['"ok"']);
    expect($result->validate()->valid)->toBeFalse();
    expect($result->commit())->toBeFalse();
});

test('discarded recovered branches and lookahead do not leak records', function () {
    $recover = $this->syntax->recoverUntil('ok', ';', true);
    $result = $this->syntax->choice($recover->then('!'), 'bad;')->parse('bad;');
    expect($result->success)->toBeTrue();
    expect($result->recoveries())->toBe([]);
    expect($result->diagnostics())->toBe([]);
    expect($this->syntax->not($recover)->parse('bad;')->hasRecovery())->toBeFalse();
});

test('a retained boundary is available to the enclosing grammar', function () {
    $result = $this->syntax->recoverUntil('ok', '}')->then('}')->parse('bad}');
    expect($result->success)->toBeTrue();
    expect($result->recoveries()[0]->span->rawText())->toBe('bad');
    expect($this->syntax->recoverUntil('ok', '}')->matchPrefix('}')->success)->toBeFalse();
    expect($this->syntax->recoverUntil('ok', '}', true)->parse('}')->success)->toBeTrue();
});

test('eof recovery is explicit and cannot create zero width repairs', function () {
    expect($this->syntax->recoverUntil('ok', ';')->parse('bad')->success)->toBeFalse();
    $result = $this->syntax->recoverUntil('ok', ';', allowEof: true)->parse('bad');
    expect($result->success)->toBeTrue();
    expect($result->recoveries()[0]->span->rawText())->toBe('bad');
    expect($this->syntax->recoverUntil('ok', ';', allowEof: true)->parse('')->success)->toBeFalse();
    expect($this->syntax->recoverUntil('ok', ';')->parse('bad')->diagnostics())->toHaveCount(1);
});

test('skip limits include consumed boundaries and count bytes', function () {
    expect($this->syntax->recoverUntil('ok', ';', true, maxSkippedBytes: 4)->parse('bad;')->success)->toBeTrue();
    expect($this->syntax->recoverUntil('ok', ';', true, maxSkippedBytes: 3)->parse('bad;')->success)->toBeFalse();
    expect($this->syntax->recoverUntil('ok', ';', allowEof: true, maxSkippedBytes: 2)
        ->parse('bad')->success)->toBeFalse();
    expect($this->syntax->recoverUntil('ok', ';', maxSkippedBytes: 0)->parse('bad;')->success)->toBeFalse();
});

test('recovery honors runtime work limits', function () {
    $this->syntax->recoverUntil('ok', ';')->parse(str_repeat('x', 1000), limits: new ParseLimits(maxWork: 20));
})->throws(ParseLimitException::class);

test('recovery scans source units and retains byte coordinates', function () {
    $source = new Utf8Source('xé😀;');
    $result = (new RecoverUntil(new Text('ok'), new Text(';'), true))->parseAll($source, 1);
    expect($result->success)->toBeTrue();
    expect($result->start)->toBe(1);
    expect($result->end)->toBe(8);
    expect($result->recoveries()[0]->span->source)->toBe($source);
    expect($result->recoveries()[0]->span->rawText())->toBe('é😀;');
    $bytes = new ByteSource("\xff\x80;");
    expect((new RecoverUntil(new Text('ok'), new Text(';'), true))->parseAll($bytes)->rawText())->toBe("\xff\x80;");
});

test('nullable boundaries are rejected by freezing and by direct runtime use', function () {
    expect(fn() => (new RecoverUntil(new Text('ok'), new Nothing()))->freeze())->toThrow(LogicException::class);
    $parser = new RecoverUntil(new Text('ok'), new Nothing());
    expect(fn() => $parser->matchPrefix(new Utf8Source('bad')))->toThrow(LogicException::class);
});

test('negative recovery limits are rejected', function () {
    new RecoverUntil(new Text('ok'), new Text(';'), maxSkippedBytes: -1);
})->throws(InvalidArgumentException::class);

test('the original failure is retained without synchronization probe expectations', function () {
    $result = $this->syntax->sequence('a', '!')->recoverUntil(';', true)->parse('ax;');
    expect($result->diagnostics()[0]->byteOffset)->toBe(1);
    expect($result->diagnostics()[0]->expected)->toBe(['"!"']);
    $failed = $this->syntax->sequence('a', '!')->recoverUntil(';')->parse('ax');
    expect($failed->success)->toBeFalse();
    expect($failed->diagnostic->byteOffset)->toBe(1);
    expect($failed->diagnostic->expected)->toBe(['"!"']);
});

test('valid operands preserve ordinary speculative diagnostics', function () {
    $operand = $this->syntax->choice($this->syntax->sequence('ab', '!'), 'a');
    $plain = $operand->then('?')->parse('abx');
    $wrapped = $operand->recoverUntil(';')->then('?')->parse('abx');
    expect($wrapped->diagnostic->byteOffset)->toBe($plain->diagnostic->byteOffset);
    expect($wrapped->diagnostic->expected)->toBe($plain->diagnostic->expected);
});

test('recovery retains selected trivia errors and serializes error node tags', function () {
    $trivia = $this->syntax->recoverUntil(' ', '~', true);
    $result = $this->syntax->sequence('a', 'b')->spacing($trivia)->parse('ax~b');
    expect($result->success)->toBeTrue();
    expect($result->hasRecovery())->toBeTrue();
    expect($result->recoveries())->toHaveCount(1);
    expect($result->node->toJson())->toContain('recovered');
    expect($result->commit())->toBeFalse();
});

test('trivia and operand recovery diagnostics stay in source order without duplicates', function () {
    $trivia = $this->syntax->recoverUntil(' ', '~', true);
    $item = $this->syntax->recoverUntil('ok', ';', true);
    $result = $this->syntax->sequence('a', $item, $item)->spacing($trivia)->parse('ax~bad;y~bad;');
    expect($result->success)->toBeTrue();
    expect(array_map(fn(Recovery $r) => $r->span->start, $result->recoveries()))->toBe([1, 3, 7, 9]);
});

test('recovery snapshots detach both operands and keep per parse records separate', function () {
    $boundary = new Text(';');
    $parser = $this->syntax->recoverUntil('ok', $boundary, true);
    $boundary->guard(fn() => false);
    $first = $parser->parse('bad;');
    $second = $parser->parse('bad;');
    expect($first->success)->toBeTrue();
    expect($second->success)->toBeTrue();
    expect($first->recoveries()[0])->not->toBe($second->recoveries()[0]);
});

test('probe exceptions restore diagnostics and leave the session usable', function () {
    $source = new Utf8Source('bad;');
    $session = new ParseSession($source);
    $session->recordFailure(0, 'previous');
    $state = $session->diagnosticState();
    $boundary = (new Text('b'))->guard(fn() => throw new LogicException('probe failed'));
    $parser = new RecoverUntil(new Text('ok'), $boundary);
    expect(fn() => $parser->recognizeInSession($source, 0, $session))->toThrow(LogicException::class, 'probe failed');
    expect($session->diagnosticState())->toBe($state);
    expect((new Text('bad;'))->recognizeInSession($source, 0, $session)->accepted)->toBeTrue();
});

test('recovery records require a nonempty region from their diagnostic source', function () {
    $source = new Utf8Source('bad');
    $diagnostic = $this->syntax->text('ok')->parse($source)->diagnostic;
    expect(fn() => new Recovery($source->span(0, 0), $diagnostic))->toThrow(InvalidArgumentException::class);
    $other = new Utf8Source('bad');
    expect(fn() => new Recovery($other->span(0, 3), $diagnostic))->toThrow(InvalidArgumentException::class);
});
