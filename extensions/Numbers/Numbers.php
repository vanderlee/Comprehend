<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Numbers;

use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerNumber;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\FloatNumber;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerValue;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\FloatValue;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

/** Integer and floating-point representations over the shared recognition runtime. */
final class Numbers implements SyntaxExtension
{
    public function rules(): iterable
    {
        foreach ([false, true] as $typed) {
            $suffix = $typed ? 'Value' : '';
            $integerClass = $typed ? IntegerValue::class : IntegerNumber::class;
            $floatClass = $typed ? FloatValue::class : FloatNumber::class;
            yield 'integer' . $suffix => static fn(
                ?int $minimum = 0,
                ?int $maximum = null,
                int $base = 10,
                bool $allowLeadingZeros = false,
                string $digits = IntegerNumber::DIGITS,
                SignPolicy $sign = SignPolicy::MINUS_ONLY,
                bool $strict = false,
            ): Parser => new $integerClass($minimum, $maximum, $base, $allowLeadingZeros, $digits, $sign, $strict);
            foreach (['binary' => 2, 'octal' => 8, 'hexadecimal' => 16] as $name => $base) {
                yield $name . $suffix => static fn(
                    ?int $minimum = 0,
                    ?int $maximum = null,
                    bool $allowLeadingZeros = false,
                    SignPolicy $sign = SignPolicy::MINUS_ONLY,
                    bool $strict = false,
                ): Parser => new $integerClass(
                    $minimum,
                    $maximum,
                    $base,
                    $allowLeadingZeros,
                    sign: $sign,
                    strict: $strict,
                );
            }
            yield 'octet' . $suffix => static fn(
                bool $allowLeadingZeros = false,
                SignPolicy $sign = SignPolicy::MINUS_ONLY,
                bool $strict = false,
            ): Parser => new $integerClass(
                0,
                255,
                10,
                $allowLeadingZeros,
                sign: $sign,
                strict: $strict,
            );
            yield 'float' . $suffix => static fn(
                ?float $minimum = 0.0,
                ?float $maximum = null,
                bool $allowLeadingZeros = false,
                bool $allowExponent = true,
                SignPolicy $sign = SignPolicy::MINUS_ONLY,
                bool $allowWholeNumbers = true,
                bool $strict = false,
            ): Parser => new $floatClass(
                $minimum,
                $maximum,
                $allowLeadingZeros,
                $allowExponent,
                $sign,
                $allowWholeNumbers,
                $strict,
            );
        }
    }

    public function fluents(): iterable
    {
        return [];
    }
}
