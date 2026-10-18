<?php

declare(strict_types=1);

namespace Tests\Extensions\Numbers;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerNumber;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;

uses(TestCase::class);

test('sign policies control spelling independently of bounds for both numeric parsers', function (
    SignPolicy $sign,
    array $accepted,
): void {
    foreach (['integer', 'float'] as $factory) {
        $parser = C::$factory(minimum: null, sign: $sign);
        foreach (['1', '+1', '-1', '0', '+0', '-0'] as $text) {
            $this->assertSame(
                in_array($text, $accepted, true),
                $parser->parse($text)->success,
                $factory . ': ' . $text,
            );
        }
    }
})->with([
    [SignPolicy::MINUS_ONLY, ['1', '-1', '0']],
    [SignPolicy::OPTIONAL, ['1', '+1', '-1', '0']],
    [SignPolicy::REQUIRED, ['+1', '-1']],
    [SignPolicy::FORBIDDEN, ['1', '0']],
]);

test('signed zero requires leading-zero permission under every sign policy', function (SignPolicy $sign): void {
    foreach (['integer', 'float'] as $factory) {
        $parser = C::$factory(minimum: null, allowLeadingZeros: true, sign: $sign);
        $this->assertSame($sign !== SignPolicy::FORBIDDEN, $parser->parse('-0')->success);
        $this->assertSame(
            in_array($sign, [SignPolicy::OPTIONAL, SignPolicy::REQUIRED], true),
            $parser->parse('+0')->success,
        );
        $this->assertFalse($parser->parse('++1')->success);
        $this->assertFalse($parser->parse('+-1')->success);
    }
})->with(SignPolicy::cases());

test('integer-family factories forward the sign policy and retain bounded values', function (string $name): void {
    $this->assertSame('+1', C::$name(sign: SignPolicy::REQUIRED)->parse('+1')->value());
    $this->assertFalse(C::$name(sign: SignPolicy::OPTIONAL)->parse('-1')->success);
    $this->assertFalse(C::$name(sign: SignPolicy::REQUIRED)->parse('1')->success);
})->with(['integer', 'binary', 'octal', 'hexadecimal', 'octet', 'float']);

test('plus remains a custom digit unless the sign policy consumes it', function (): void {
    $this->assertSame('+', C::integer(base: 2, digits: '0+')->parse('+')->value());
    $this->assertSame('+', C::integer(base: 2, digits: '0+', sign: SignPolicy::FORBIDDEN)->parse('+')->value());
    foreach ([SignPolicy::OPTIONAL, SignPolicy::REQUIRED] as $sign) {
        try {
            new IntegerNumber(base: 2, digits: '0+', sign: $sign);
            $this->fail('Expected a conflicting sign alphabet to fail');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('plus sign', $error->getMessage());
        }
    }
});
