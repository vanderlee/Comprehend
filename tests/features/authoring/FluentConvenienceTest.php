<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('map text is lazy cached and can return typed values', function (): void {
    $calls = 0;
    $effects = 0;
    $base = C::text('42');
    $mapped = $base->mapText(static function (string $text) use (&$calls): int {
        ++$calls;

        return (int) $text;
    })->onCommit(static function () use (&$effects): void {
        ++$effects;
    });
    $result = $mapped->parse('42');
    $this->assertTrue($result->ok);
    $this->assertSame(0, $calls);
    $this->assertSame(42, $result->output());
    $this->assertSame(42, $result->value());
    $this->assertSame(1, $calls);
    $this->assertSame('42', $result->rawText());
    $this->assertSame('42', $base->parse('42')->output());
    $this->assertSame(0, $effects);
    $this->assertFalse($result->isCommitted());
    $this->assertNull($mapped->parse('no')->output());
    $this->assertSame(1, $calls);
    $this->assertTrue($result->commit());
    $this->assertSame(1, $effects);
});

test('map text accepts function names and always receives original text', function (): void {
    $mapped = C::text('ab')->mapText(static fn(string $text): string => '[' . $text . ']')
        ->mapText(mapper: 'strtoupper');
    $this->assertSame('AB', $mapped->parse('ab')->output());
    $this->assertSame('ab', $mapped->parse('ab')->rawText());
    $this->assertNull(C::text('x')->mapText(static fn(string $text) => null)->parse('x')->output());
    $this->assertSame(3, C::text("\u{00e9}!")->mapText('strlen')->parse("\u{00e9}!")->output());
});

test('prepend retains order names and immutable reuse', function (): void {
    $base = C::text('b')->named('body');
    $grammar = $base->prepend('a', '[')->text(']');
    $result = $grammar->parse('a[b]');
    $this->assertTrue($result->ok);
    $this->assertSame('a[b]', $result->rawText());
    $this->assertSame('b', $result->ast()->firstNamed('body')->rawText());
    $this->assertSame(2, $result->ast()->firstNamed('body')->span->start);
    $this->assertTrue($base->parse('b')->ok);
    $this->assertFalse($base->parse('a[b]')->ok);
    $this->assertSame($base, $base->prepend());
    $this->assertTrue($base->prepend(before: 'a')->parse('ab')->ok);
});

test('prepend detaches object inputs and retains custom syntax scope', function (): void {
    $prefix = new Sequence(new Text('a'));
    $syntax = C::syntax()->define('suffix', static fn(): C => C::text('!'));
    $base = $syntax->text('b');
    $grammar = $base->prepend($prefix, '[')->suffix();
    $prefix->add(new Text('x'));
    $this->assertTrue($grammar->parse('a[b!')->ok);
    $this->assertFalse($grammar->parse('ax[b!')->ok);
    $this->assertTrue($base->suffix()->parse('b!')->ok);
    $this->assertSame('B!', $base->mapText('strtoupper')->suffix()->mapText('strtoupper')->parse('b!')->output());
});
