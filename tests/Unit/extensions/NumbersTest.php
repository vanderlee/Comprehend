<?php

declare(strict_types=1);

namespace Tests\Extensions;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\Numbers;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\FloatNumber;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerNumber;

uses(TestCase::class);

test('numeric factories discover rules and append through scoped and global calls', function (
    string $name,
    string $input,
): void {
    $this->assertNotContains($name, C::syntaxNames());
    $this->assertContains($name, C::syntax()->names());
    $this->assertInstanceOf($name === 'float' ? FloatNumber::class : IntegerNumber::class, C::$name()->parser());
    $this->assertSame($input, C::$name()->parse($input)->value());
    $this->assertSame('@' . $input, C::text('@')->$name()->parse('@' . $input)->value());
    $syntax = C::syntax()->defineRule('suffix', static fn(): C => C::text('!'));
    $this->assertSame($input . '!', $syntax->$name()->suffix()->parse($input . '!')->value());
    $this->assertSame('@' . $input, $syntax->text('@')->$name()->parse('@' . $input)->value());
    $this->assertSame([], iterator_to_array((new Numbers())->fluents()));
    $this->assertNull($syntax->fluent($name));
})->with([
    ['integer', '12'], ['binary', '101'], ['octal', '17'], ['hexadecimal', 'ff'], ['octet', '255'],
    ['float', '1.25'],
]);

test('integer factory options retain named arguments and textual values', function (): void {
    $this->assertSame('βα', C::integer(
        digits: 'αβ',
        base: 2,
        maximum: 2,
        minimum: 2,
    )->parse('βα')->value());
    $this->assertSame('-10', C::binary(maximum: -2, minimum: -2)->parse('-10')->value());
    $this->assertSame('10', C::octal(maximum: 8, minimum: 8)->parse('10')->value());
    $this->assertSame('FF', C::hexadecimal(minimum: 255, maximum: 255)->caseSensitive(false)->parse('FF')->value());
    $this->assertFalse(C::octet()->parse('256')->success);
    $this->assertFalse(C::octet()->parse('025')->success);
    $this->assertSame('025', C::octet(allowLeadingZeros: true)->parse('025')->value());
});

test('explicit integer overrides remain local and explicit extension installation is supported', function (): void {
    $base = C::syntax();
    $syntax = $base->defineRule('integer', static fn(): C => C::text('custom'));
    $this->assertSame('custom', $syntax->integer()->parse('custom')->value());
    $this->assertSame('1', $base->integer()->parse('1')->value());
    $this->assertSame('1', C::integer()->parse('1')->value());
    $this->assertSame('1', $syntax->extend(new Numbers())->integer()->parse('1')->value());
});
