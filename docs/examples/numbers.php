<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy as S;

$number = C::float(minimum: null, sign: S::OPTIONAL);
if ($number->parse('+1.25e-2')->value() !== '+1.25e-2') {
    throw new RuntimeException('Float spelling was not preserved');
}
if ($number->map('floatval')->parse('+1.25e-2')->value() !== 0.0125) {
    throw new RuntimeException('Explicit float projection failed');
}
if (C::integer(sign: S::REQUIRED)->parse('+12')->value() !== '+12') {
    throw new RuntimeException('Required integer sign failed');
}
$decimal = C::float(allowExponent: false);
if ($decimal->parse('1e3')->success || $decimal->matchPrefix('1e3')->value() !== '1') {
    throw new RuntimeException('Exponent toggle changed prefix semantics');
}
if (C::float(maximum: 2)->matchPrefix('12.5')->value() !== '1') {
    throw new RuntimeException('Float bounds did not retain prefix semantics');
}
$fractional = C::float(allowWholeNumbers: false);
if ($fractional->parse('12')->success || $fractional->parse('1e3')->value() !== '1e3') {
    throw new RuntimeException('Float spelling restriction failed');
}
if (C::float(maximum: 2, allowWholeNumbers: false)->matchPrefix('12.5')->success) {
    throw new RuntimeException('Float fallback accepted a whole-number spelling');
}
if (C::float(minimum: null)->parse('-0.0')->success) {
    throw new RuntimeException('Signed zero must require leading-zero permission');
}
if (C::float(minimum: null, allowLeadingZeros: true)->parse('-0.0')->value() !== '-0.0') {
    throw new RuntimeException('Signed zero permission failed');
}

$typed = C::hexadecimalValue(strict: true)->parse('ff');
if ($typed->value() !== 255 || $typed->rawText() !== 'ff') {
    throw new RuntimeException('Typed numeric projection changed source spelling');
}
if (C::floatValue()->parse('1.25e2')->value() !== 125.0) {
    throw new RuntimeException('Typed float projection failed');
}
if (C::integer(maximum: 255, strict: true)->matchPrefix('256')->success) {
    throw new RuntimeException('Strict integer matching shortened an invalid token');
}
if (C::binary(strict: true)->matchPrefix('102')->success) {
    throw new RuntimeException('Strict matching accepted an out-of-base digit');
}
if (C::integerValue(strict: true)->matchPrefix('12kg')->value() !== 12) {
    throw new RuntimeException('Strict matching consumed an unrelated suffix');
}

echo "Numbers, typed values, strict matching and source spelling verified\n";
