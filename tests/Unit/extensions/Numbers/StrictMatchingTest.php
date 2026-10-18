<?php

declare(strict_types=1);

namespace Tests\Extensions\Numbers;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;

uses(TestCase::class);

test('strict integers reject numeric continuations without changing default prefixes', function (
    string $factory,
    string $text,
    string $prefix,
): void {
    $this->assertSame($prefix, C::$factory()->matchPrefix($text)->rawText());
    $this->assertFalse(C::$factory(strict: true)->matchPrefix($text)->success);
    $this->assertFalse(C::{$factory . 'Value'}(strict: true)->matchPrefix($text)->success);
})->with([
    ['integer', '01', '0'], ['binary', '102', '10'], ['octal', '178', '17'],
    ['hexadecimal', '0ff', '0'], ['octet', '256', '25'],
    ['integer', (string) PHP_INT_MAX . '0', (string) PHP_INT_MAX],
]);

test('strict integer bounds signs custom alphabets and delimiters stay independent', function (): void {
    $this->assertFalse(C::integer(maximum: 255, strict: true)->matchPrefix('256')->success);
    $this->assertSame(12, C::integerValue(strict: true)->matchPrefix('12kg')->value());
    $this->assertSame(12, C::integerValue(strict: true)->matchPrefix('12+3')->value());
    $this->assertSame(12, C::integerValue(strict: true)->matchPrefix('12.5')->value());
    $this->assertSame(2, C::integerValue(base: 2, digits: 'αβ', strict: true)->parse('βα')->value());
    $this->assertFalse(C::integer(base: 2, digits: 'αβ', strict: true)->matchPrefix('βα2')->success);
    $this->assertFalse(C::integer(minimum: null, strict: true)->matchPrefix('-0')->success);
    $this->assertSame(0, C::integerValue(allowLeadingZeros: true, strict: true)->parse('-00')->value());
    $this->assertSame(12, C::integerValue(sign: SignPolicy::REQUIRED, strict: true)->parse('+12')->value());
    $this->assertSame(255, C::hexadecimalValue(strict: true)->caseSensitive(false)->matchPrefix('FFkg')->value());
});

test('strict floats reject malformed and disabled numeric continuations', function (string $text): void {
    $this->assertFalse(C::float(strict: true)->matchPrefix($text)->success);
    $this->assertFalse(C::floatValue(strict: true)->matchPrefix($text)->success);
})->with(['01.5', '1e', '1e+', '1e-', '1.2.3', '1e2e3', '1e999', '-0.0']);

test('strict floats preserve valid exponents boundaries and whole-number options', function (): void {
    $this->assertFalse(C::float(allowExponent: false, strict: true)->matchPrefix('1e3')->success);
    $this->assertFalse(C::float(maximum: 2, strict: true)->matchPrefix('1.5e3')->success);
    $this->assertFalse(C::float(allowWholeNumbers: false, strict: true)->matchPrefix('12kg')->success);
    $this->assertSame(1000.0, C::floatValue(allowWholeNumbers: false, strict: true)->matchPrefix('1e3,')->value());
    $this->assertSame(12.5, C::floatValue(strict: true)->matchPrefix('12.5kg')->value());
    $this->assertSame(12.0, C::floatValue(strict: true)->matchPrefix('12+3')->value());
    $this->assertSame(12.0, C::floatValue(strict: true)->matchPrefix('12-3')->value());
    $this->assertSame(0.0, C::floatValue(strict: true)->parse('1e-9999')->value());
    $this->assertSame('1.5', C::float(maximum: 2)->matchPrefix('1.5e3')->value());
    $this->assertSame('1', C::float(allowExponent: false)->matchPrefix('1e3')->value());
});

test('strict failures preserve diagnostic start offsets and participate in work limits', function (): void {
    $result = C::integer(strict: true)->matchPrefix('é01', byteOffset: 2);
    $this->assertFalse($result->success);
    $this->assertNotEmpty($result->diagnostics());
    $this->assertSame(2, $result->diagnostics()[0]->byteOffset);
    $this->expectException(ParseLimitException::class);
    C::integer(strict: true)->parse('123', limits: new ParseLimits(maxWork: 5));
});
