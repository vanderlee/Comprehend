<?php

declare(strict_types=1);

namespace Tests\Extensions;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Extraction;
use Vanderlee\Comprehend\Extensions\Expression\Operator as O;
use Vanderlee\Comprehend\Extensions\Expressions;

uses(TestCase::class);

test('fluent expressions use the current value as the primary and retain the vocabulary', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $calls = 0;
    $number = $syntax->regex('/[0-9]+/A')->mapText('intval');
    $expression = $number->operatorExpression(
        O::infixLeft('+', 10, static function (int $a, int $b) use (&$calls): int {
            ++$calls;
            return $a + $b;
        }),
        O::infixLeft('*', 20, static fn(int $a, int $b): int => $a * $b),
    )->spacing(Extraction::syntax()->text(' ')->many());
    $result = $expression->parse('2 + 3 * 4');
    $this->assertTrue($result->success);
    $this->assertSame('2 + 3 * 4', $result->rawText());
    $this->assertSame(0, $calls);
    $this->assertSame(14, $result->value());
    $this->assertSame(14, $result->value());
    $this->assertSame(1, $calls);
    $this->assertSame(2, $number->parse('2')->value());
    $this->assertFalse($number->parse('2+3')->success);
    $this->assertSame(7, $number->operatorExpression()->parse('7')->value());
    $choice = $number->expressionChoice(Extraction::syntax()->text('x')->mapText(static fn(): int => 99));
    $this->assertSame(7, $choice->parse('7')->value());
    $this->assertSame(99, $choice->parse('x')->value());
    $this->assertSame(99, $choice->expressionChoice('y')->parse('x')->value());
    $this->assertFalse($choice->parse('7x')->success);
});

test('expression tables respect precedence associativity and repeated unary operators', function (
    string $input,
    int $expected,
): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $number = Extraction::syntax()->regex('/[0-9]+/A')->mapText(static fn(string $text): int => (int) $text);
    $parser = $syntax->operatorExpression(
        $number,
        O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b),
        O::infixLeft('-', 10, static fn(int $a, int $b): int => $a - $b),
        O::infixLeft('*', 20, static fn(int $a, int $b): int => $a * $b),
        O::prefix('-', 30, static fn(int $a): int => -$a),
        O::prefix('~', 30, static fn(int $a): int => ~$a),
        O::infixRight('^', 40, static fn(int $a, int $b): int => $a ** $b),
        O::postfix('!', 50, static fn(int $a): int => $a + 1),
    )->spacing(Extraction::syntax()->text(' ')->many());
    $result = $parser->parse($input);
    $this->assertTrue($result->success);
    $this->assertSame($expected, $result->value());
    $this->assertSame($input, $result->rawText());
})->with([
    ['42', 42], ['2+3*4', 14], ['10-3-2', 5], ['2^3^2', 512],
    ['-2^2', -4], ['--2', 2], ['-~2', 3], ['~-2', 1],
    ['2!!', 4], ['-2!', -3], ['2!*3', 9], ['2 + 3 * 4', 14],
    ['- - 2', 2], ['2 ! !', 4], ['2 ^ 3 ^ 2', 512],
]);

test('non associative operators reject chains without changing prefix semantics', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $number = Extraction::syntax()->integer()->mapText(static fn(string $text): int => (int) $text);
    $parser = $syntax->operatorExpression($number, O::infixNone('<', 10, static fn(int $a, int $b): bool => $a < $b));
    $this->assertTrue($parser->parse('1<2')->value());
    $this->assertFalse($parser->parse('1<2<3')->success);
    $this->assertSame('1<2', $parser->matchPrefix('1<2<3')->rawText());
});

test('expression lists do not silently trim boundary whitespace or accept missing operands', function (): void {
    $syntax = Extraction::syntax()->extend(new Expressions());
    $number = Extraction::syntax()->integer()->mapText(static fn(string $text): int => (int) $text);
    $parser = $syntax->operatorExpression(
        $number,
        O::prefix('-', 20, static fn(int $a): int => -$a),
        O::infixLeft('+', 10, static fn(int $a, int $b): int => $a + $b),
        O::postfix('!', 30, static fn(int $a): int => $a + 1),
    )->spacing(Extraction::syntax()->text(' ')->many());
    foreach (['', '1+', '-', '!', '+1', ' 1', '1 ', '- 1 ', '1 + '] as $input) {
        $this->assertFalse($parser->parse($input)->success, $input);
    }
    $prefix = $parser->matchPrefix('1 + nope');
    $this->assertSame('1', $prefix->rawText());
    $this->assertNull($prefix->diagnostic);
});

test('one precedence level cannot mix extraction kinds', function (): void {
    $this->expectException(InvalidArgumentException::class);
    Extraction::syntax()->extend(new Expressions())->operatorExpression(
        'a',
        O::infixLeft('+', 10, static fn($a, $b) => $a),
        O::infixRight('^', 10, static fn($a, $b) => $b),
    );
});

test('nullable operator syntax is rejected even when adjacent operands consume', function (): void {
    $this->expectException(LogicException::class);
    Extraction::syntax()->extend(new Expressions())->operatorExpression(
        'a',
        O::infixLeft(Extraction::syntax()->nothing(), 10, static fn($a, $b) => $a),
    )->freeze();
});

test('expression extension loads automatically and zero operator tables preserve primary values', function (): void {
    $base = Extraction::syntax();
    $syntax = $base->extend(new Expressions());
    $this->assertContains('operatorexpression', $base->names());
    $this->assertSame(7, $syntax->operatorExpression(
        Extraction::syntax()->text('7')->mapText('intval'),
    )->parse('7')->value());
    $this->assertSame(7, $syntax->expressionChoice(
        Extraction::syntax()->text('7')->mapText('intval'),
        'x',
    )->parse('7')->value());
    $this->assertSame('a', $base->operatorExpression('a')->parse('a')->value());
});
