<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
use Vanderlee\Comprehend\Extensions\Recovery\Recovery as RecoveryExtension;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverDelimited;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverItem;
use Vanderlee\Comprehend\Extensions\Recovery\Parser\RecoverWith;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Extensions\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Result\Recovery;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\Utf8Source;

beforeEach(function (): void {
    $this->syntax = Extraction::syntax()->extend(new RecoveryExtension());
});

test('delimited recovery preserves valid values and owns both delimiters', function () {
    $grammar = $this->syntax->regex('/[0-9]+/A')->mapText('intval')->recoverDelimited('(', ')');
    $valid = $grammar->parse('(42)');
    expect($valid->success)->toBeTrue();
    expect($valid->hasRecovery())->toBeFalse();
    expect($valid->value())->toBe(42);
    expect($valid->rawText())->toBe('(42)');
    expect($valid->commit())->toBeTrue();
    expect($grammar->parse('bad)')->success)->toBeFalse();
});

test('delimited recovery ignores nested closes in strings and comments', function () {
    $ignore = $this->syntax->choice(
        Extraction::syntax()->regex('/"(?:[^"\\\\]|\\\\.)*"/A'),
        Extraction::syntax()->regex('~/\*.*?\*/~As'),
    );
    $grammar = $this->syntax->text('valid')->recoverDelimited('(', ')', ignore: $ignore)->then('tail');
    $text = '(bad(nested) ")" /* ) */)tail';
    $result = $grammar->parse($text);
    expect($result->success)->toBeTrue();
    expect($result->recoveries())->toHaveCount(1);
    expect($result->recoveries()[0]->span->rawText())->toBe('(bad(nested) ")" /* ) */)');
    expect($result->rawText())->toBe($text);
    expect($result->commit())->toBeFalse();
    expect($result->node->toJson())->toContain('recovered');
});

test('delimited recovery has explicit eof and byte limit policies', function () {
    expect($this->syntax->text('ok')->recoverDelimited('(', ')')->parse('(bad')->success)->toBeFalse();
    expect($this->syntax->text('ok')->recoverDelimited('(', ')', allowEof: true)->parse('(bad')->success)->toBeTrue();
    expect($this->syntax->text('ok')->recoverDelimited('(', ')', maxSkippedBytes: 5)
        ->parse('(bad)')->success)->toBeTrue();
    expect($this->syntax->text('ok')->recoverDelimited('(', ')', maxSkippedBytes: 4)
        ->parse('(bad)')->success)->toBeFalse();
    $limited = $this->syntax->text('ok')->recoverDelimited('(', ')', allowEof: true, maxSkippedBytes: 2);
    expect($limited->parse('((bad')->success)->toBeFalse();
});

test('delimited recovery matches multibyte and multicharacter delimiters', function () {
    $result = $this->syntax->recoverDelimited('ok', '«', '»')->matchPrefix('x«é«bad»😀»tail', 1);
    expect($result->success)->toBeTrue();
    expect($result->rawText())->toBe('«é«bad»😀»');
    expect($result->recoveries()[0]->span->start)->toBe(1);
    expect($this->syntax->recoverDelimited('ok', 'BEGIN', 'END')->parse('BEGINxBEGINyENDzEND')->success)->toBeTrue();
});

test('delimited recovery applies scoped spacing during valid recognition', function () {
    $result = $this->syntax->text('ok')->mapValue(fn() => 42)->recoverDelimited('(', ')')
        ->spacing($this->syntax->text(' ')->many())->parse('( ok )');
    expect($result->success)->toBeTrue();
    expect($result->value())->toBe(42);
    expect($result->hasRecovery())->toBeFalse();
});

test('item recovery returns a typed list with explicit error values', function () {
    $items = $this->syntax->regex('/[0-9]+/A')->mapText('intval')->recoverItem(',', ']')->between('[', ']');
    $result = $items->parse('[1,???,3]');
    expect($result->success)->toBeTrue();
    $values = $result->value();
    expect($values[0])->toBe(1);
    expect($values[1])->toBeInstanceOf(Recovery::class);
    expect($values[1]->span->rawText())->toBe('???');
    expect($values[2])->toBe(3);
    expect($result->recoveries())->toHaveCount(1);
    expect($result->commit())->toBeFalse();
    expect($items->parse('[1,2,3]')->value())->toBe([1, 2, 3]);
    expect($items->parse('[]')->value())->toBe([]);
});

test('item recovery treats invalid suffixes as part of the malformed item', function () {
    $list = $this->syntax->regex('/[0-9]+/A')->mapText('intval')->recoverItem(',', ']')->between('[', ']');
    $result = $list->parse('[12oops,3,broken]');
    expect($result->success)->toBeTrue();
    expect(array_map(fn(Recovery $r) => $r->span->rawText(), $result->recoveries()))->toBe(['12oops', 'broken']);
    expect($result->value()[1])->toBe(3);
});

test('item recovery distinguishes missing items trailing separators and the closing delimiter', function () {
    $list = $this->syntax->regex('/[0-9]+/A')->recoverItem(',', ']')->between('[', ']');
    expect($list->parse('[1,,3]')->success)->toBeFalse();
    expect($list->parse('[,1]')->success)->toBeFalse();
    expect($list->parse('[1,]')->success)->toBeFalse();
    $trailing = $this->syntax->regex('/[0-9]+/A')->recoverItem(',', ']', allowTrailing: true)->between('[', ']');
    expect($trailing->parse('[1,]')->success)->toBeTrue();
    expect($trailing->parse('[1,]')->hasRecovery())->toBeFalse();
    expect($trailing->parse('[1,,]')->success)->toBeFalse();
    expect($trailing->parse('[1')->success)->toBeFalse();
});

test('item recovery honors bounds spacing and the outer closing delimiter', function () {
    $list = $this->syntax->regex('/[0-9]+/A')->mapText('intval')->recoverItem(',', ']', min: 1, max: 2)
        ->between('[', ']')->spacing($this->syntax->text(' ')->many());
    $result = $list->parse('[ 1 , bad ]');
    expect($result->success)->toBeTrue();
    expect($result->value()[0])->toBe(1);
    expect($result->recoveries()[0]->span->rawText())->toBe('bad ');
    expect($list->parse('[]')->success)->toBeFalse();
    expect($list->parse('[1,2,3]')->success)->toBeFalse();
    expect($list->parse('[bad]')->success)->toBeTrue();
});

test('item recovery can ignore separators inside complete quoted regions', function () {
    $list = $this->syntax->text('ok')->recoverItem(',', ']', ignore: $this->syntax->regex('/"[^"]*"/A'))
        ->between('[', ']');
    $result = $list->parse('[bad"a,b]c",ok]');
    expect($result->success)->toBeTrue();
    expect($result->value()[1])->toBe('ok');
    expect($result->recoveries()[0]->span->rawText())->toBe('bad"a,b]c"');
});

test('custom recovery receives the original diagnostic and uses registered probes', function () {
    $seen = null;
    $grammar = $this->syntax->text('valid')->recoverWith(
        function (RecoveryContext $context) use (&$seen): ?Span {
            $seen = $context->failure;
            return $context->scanUntil('declaration');
        },
        probes: ['declaration' => $this->syntax->text('function')],
    )->then('function');
    $result = $grammar->parse('broken function');
    expect($result->success)->toBeTrue();
    expect($seen)->toBe($result->diagnostics()[0]);
    expect($seen->expected)->toBe(['"valid"']);
    expect($result->recoveries()[0]->span->rawText())->toBe('broken ');
});

test('custom recovery can decline and never runs after valid recognition', function () {
    $calls = 0;
    $parser = $this->syntax->text('ok')->recoverWith(function (RecoveryContext $context) use (&$calls): ?Span {
        ++$calls;
        return null;
    });
    expect($parser->parse('ok')->success)->toBeTrue();
    expect($calls)->toBe(0);
    expect($parser->parse('bad')->success)->toBeFalse();
    expect($calls)->toBe(1);
});

test('custom recovery rejects foreign empty reversed and partial code point regions', function () {
    $other = new Utf8Source('bad');
    $strategies = [
        fn(RecoveryContext $c): Span => $other->span(0, 3),
        fn(RecoveryContext $c): Span => $c->source->span($c->start, $c->start),
        fn(RecoveryContext $c): Span => $c->source->span($c->start + 1, $c->start + 2),
        fn(RecoveryContext $c): Span => $c->source->span($c->start, $c->start + 1),
    ];
    foreach ($strategies as $strategy) {
        expect(fn() => $this->syntax->text('ok')->recoverWith($strategy)
        ->parse('ébad'))->toThrow(LogicException::class);
    }
    expect(fn() => $this->syntax->text('ok')->recoverWith(fn() => 42)->parse('bad'))->toThrow(TypeError::class);
});

test('custom recovery contexts expire and enforce registered probe names', function () {
    $saved = null;
    $parser = $this->syntax->text('ok')->recoverWith(function (RecoveryContext $c) use (&$saved): ?Span {
        $saved = $c;
        return $c->regionTo($c->source->byteLength());
    });
    expect($parser->parse('bad')->success)->toBeTrue();
    expect(fn() => $saved->readUnit(0))->toThrow(LogicException::class);
    expect(fn() => $this->syntax->text('ok')->recoverWith(fn(RecoveryContext $c) => $c->probe('missing', 0))
        ->parse('bad'))
        ->toThrow(InvalidArgumentException::class);
});

test('all recovery strategies account for work and enforce consuming probes', function () {
    $delimited = $this->syntax->text('ok')->recoverDelimited('(', ')');
    expect(fn() => $delimited->parse('(' . str_repeat('x', 200) . ')', limits: new ParseLimits(maxWork: 25)))
        ->toThrow(ParseLimitException::class);
    expect(fn() => $this->syntax->text('ok')->recoverWith(function (RecoveryContext $c): ?Span {
        $c->consumeWork(100);
        return $c->regionTo(1);
    })->parse('bad', limits: new ParseLimits(maxWork: 10)))->toThrow(ParseLimitException::class);
    expect(fn() => (new RecoverDelimited(new Text('ok'), new Nothing(), new Text(')')))->freeze())
        ->toThrow(LogicException::class);
    expect(fn() => (new RecoverItem(new Text('ok'), new Text(','), new Nothing()))->freeze())
        ->toThrow(LogicException::class);
});

test('registered probes are snapshotted and do not run semantic operations or effects', function () {
    $probe = new Text(';');
    $probe->mapValue(fn() => throw new LogicException('value'));
    $probe->onCommit(fn() => throw new LogicException('effect'));
    $parser = $this->syntax->text('ok')->recoverWith(
        fn(RecoveryContext $c): ?Span => $c->scanUntil('stop', true),
        ['stop' => $probe],
    );
    $probe->guard(fn() => false);
    $result = $parser->parse('bad;');
    expect($result->success)->toBeTrue();
    expect($result->commit())->toBeFalse();
});

test('custom strategy exceptions restore session diagnostics and parser scopes', function () {
    $source = new ByteSource('bad;');
    $session = new ParseSession($source);
    $session->recordFailure(0, 'previous');
    $before = $session->diagnosticState();
    $parser = new RecoverWith(new Text('ok'), function (RecoveryContext $context): ?Span {
        $context->probe('stop', 0);
        throw new LogicException('strategy');
    }, ['stop' => new Text(';')]);
    expect(fn() => $parser->recognizeInSession($source, 0, $session))->toThrow(LogicException::class, 'strategy');
    expect($session->diagnosticState())->toBe($before);
    expect((new Text('bad;'))->recognizeInSession($source, 0, $session)->accepted)->toBeTrue();
});
