<?php

declare(strict_types=1);

namespace Tests\Extensions\Numbers\Parser;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\FloatNumber;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('float can require a decimal point or an enabled exponent', function (
    string $text,
    bool $withExponent,
    bool $withoutExponent,
): void {
    $this->assertSame($withExponent, C::float(allowWholeNumbers: false)->parse($text)->success);
    $this->assertSame($withoutExponent, C::float(
        allowExponent: false,
        allowWholeNumbers: false,
    )->parse($text)->success);
})->with([
    ['12', false, false], ['0', false, false], ['12.0', true, true], ['.5', true, true],
    ['12.', true, true], ['1e3', true, false], ['1E+03', true, false], ['12.0e3', true, false],
    ['1e', false, false], ['1e+', false, false], ['.', false, false],
]);

test('float prefix fallback cannot bypass the whole-number spelling restriction', function (): void {
    $restricted = C::float(maximum: 2, allowWholeNumbers: false);
    $this->assertFalse($restricted->matchPrefix('12.5')->success);
    $this->assertSame('1.5', $restricted->matchPrefix('1.5e3')->value());
    $this->assertSame('1e99', C::float(allowWholeNumbers: false)->matchPrefix('1e999')->value());
    $this->assertFalse(C::float(allowWholeNumbers: false)->matchPrefix('12!')->success);
    $this->assertFalse(C::float(allowWholeNumbers: false)->matchPrefix('1e+')->success);
    $this->assertFalse(C::float(allowExponent: false, allowWholeNumbers: false)->matchPrefix('1e3')->success);
    $this->assertSame('12.', C::float(allowWholeNumbers: false)->matchPrefix('12.!')->value());
    $this->assertSame('1', C::float(maximum: 2)->matchPrefix('12.5')->value());
});

test('whole-number restriction preserves signs leading zeros and authoring scopes', function (): void {
    $parser = C::float(sign: SignPolicy::OPTIONAL, allowWholeNumbers: false);
    $this->assertSame('+0.5', $parser->parse('+0.5')->value());
    $this->assertFalse($parser->parse('+12')->success);
    $this->assertFalse($parser->parse('00.5')->success);
    $this->assertSame('00.5', C::float(allowLeadingZeros: true, allowWholeNumbers: false)->parse('00.5')->value());
    $this->assertFalse(C::float(minimum: null, allowWholeNumbers: false)->parse('-0.0')->success);
    $this->assertFalse(C::float(sign: SignPolicy::REQUIRED, allowWholeNumbers: false)->parse('.5')->success);
    $this->assertSame('+1.5', C::float(sign: SignPolicy::REQUIRED, allowWholeNumbers: false)->parse('+1.5')->value());
    $this->assertSame('1.5', C::syntax()->float(allowWholeNumbers: false)->parse('1.5')->value());
    $this->assertSame('@1.5', C::text('@')->float(allowWholeNumbers: false)->parse('@1.5')->value());
    $this->assertSame('1.5', C::of(new FloatNumber(
        0.0,
        null,
        false,
        true,
        SignPolicy::MINUS_ONLY,
        false,
    ))->parse('1.5')->value());
    $this->assertSame('12', C::of(new FloatNumber(
        0.0,
        null,
        false,
        true,
        SignPolicy::MINUS_ONLY,
    ))->parse('12')->value());
});

test('float decimal and exponent spellings retain their original text', function (string $text): void {
    foreach ([$text, new ByteSource($text)] as $input) {
        $result = C::float(minimum: null)->parse($input);
        $this->assertTrue($result->success);
        $this->assertSame($text, $result->value());
        $this->assertSame($text, $result->rawText());
    }
})->with([
    '0', '12', '12.5', '.5', '12.', '0.5', '1e3', '1E+03', '1.2e-3', '.5E2', '12.e2',
    '-12', '-.5', '-0.5', '1e-9999', '-1e-9999',
]);

test('float rejects malformed or unsupported complete inputs', function (string $text): void {
    $this->assertFalse(C::float(minimum: null)->parse($text)->success);
})->with([
    '', '.', '+', '-', '1e', '1e+', '1e-', '--1', '+1', '01', '00.5', '1_000', '1,5',
    'INF', '-INF', 'NaN', '0x1', ' 1', '1 ', '١.٥', '-0', '-0.0', '-.0', '-0e3', '1e9999',
]);

test('float options independently control exponent and leading zeros', function (): void {
    $plain = C::float(allowExponent: false);
    $this->assertSame('1.2', $plain->matchPrefix('1.2e3')->value());
    $this->assertFalse($plain->parse('1.2e3')->success);
    $this->assertSame('01.20e03', C::float(allowLeadingZeros: true)->parse('01.20e03')->value());
    $this->assertSame('-0.00e2', C::float(
        minimum: null,
        allowLeadingZeros: true,
    )->parse('-0.00e2')->value());
    $this->assertFalse(C::float()->parse('-0.5')->success);
    $this->assertSame('1e-2', C::float(sign: SignPolicy::FORBIDDEN)->parse('1e-2')->value());
});

test('float bounds and overflow select only complete numeric prefixes', function (): void {
    $bounded = C::float(maximum: 2.0);
    $this->assertSame('1', $bounded->matchPrefix('12.5')->value());
    $this->assertFalse($bounded->parse('12.5')->success);
    $this->assertSame('1.5', $bounded->matchPrefix('1.5e3')->value());
    $this->assertSame('1e99', C::float()->matchPrefix('1e999')->value());
    $this->assertSame('-1.25', C::float(minimum: -1.25, maximum: -1.25)->parse('-1.25')->value());
    $this->assertFalse(C::float(minimum: 2.0, maximum: 3.0)->parse('1.5')->success);
});

test('float bounds must be ordered and finite', function (?float $minimum, ?float $maximum): void {
    $this->expectException(InvalidArgumentException::class);
    new FloatNumber($minimum, $maximum);
})->with([[2.0, 1.0], [INF, null], [-INF, null], [NAN, null], [null, INF], [null, NAN]]);

test('float recognition preserves byte spans diagnostics lazy projections and explicit effects', function (): void {
    $calls = $effects = 0;
    $parser = C::float()->map(static function (string $text) use (&$calls): float {
        ++$calls;

        return (float) $text;
    })->onCommit(static function () use (&$effects): void {
        ++$effects;
    });
    $result = $parser->freeze()->matchPrefix('é1.5!', byteOffset: 2);
    $this->assertSame([2, 5], [$result->node->span->start, $result->node->span->end]);
    $this->assertSame('1.5', $result->rawText());
    $this->assertSame(0, $calls);
    $this->assertSame(1.5, $result->value());
    $this->assertSame(1.5, $result->value());
    $this->assertSame(1, $calls);
    $this->assertSame(0, $effects);
    $this->assertTrue($result->commit());
    $this->assertSame(1, $effects);
    $this->assertFalse(C::float()->parse('x')->success);
    $this->assertNotEmpty(C::float()->parse('x')->diagnostics());
});

test('float file subjects preserve byte offsets and recognition work limits', function (): void {
    $stream = tmpfile();
    fwrite($stream, "é1.25\xff");
    $source = new ByteFileSource($stream);
    fclose($stream);
    try {
        $result = C::float()->matchPrefix($source, byteOffset: 2);
        $this->assertSame('1.25', $result->value());
        $this->assertSame([2, 6], [$result->node->span->start, $result->node->span->end]);
        $this->expectException(ParseLimitException::class);
        C::float()->parse('123', limits: new ParseLimits(maxWork: 5));
    } finally {
        $source->close();
    }
});
