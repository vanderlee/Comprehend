<?php

declare(strict_types=1);

namespace Tests\Extensions\Numbers;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerValue;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\FloatValue;
use Vanderlee\Comprehend\Extensions\Numbers\SignPolicy;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(TestCase::class);

test('typed numeric factories return native values and preserve spelling', function (
    string $factory,
    string $text,
    int|float $expected,
): void {
    $result = C::$factory()->parse($text);
    $this->assertSame($expected, $result->value());
    $this->assertSame($expected, $result->value());
    $this->assertSame($text, $result->rawText());
    $this->assertSame([0, strlen($text)], [$result->node->span->start, $result->node->span->end]);
    $this->assertSame($expected, C::syntax()->$factory()->freeze()->parse($text)->value());
})->with([
    ['integerValue', '42', 42], ['binaryValue', '101', 5], ['octalValue', '17', 15],
    ['hexadecimalValue', 'ff', 255], ['octetValue', '255', 255], ['floatValue', '1.25e2', 125.0],
]);

test('integer values retain custom alphabets case scopes signs and integer limits', function (): void {
    $this->assertSame(2, C::integerValue(base: 2, digits: 'αβ')->parse('βα')->value());
    $this->assertSame(2, C::integerValue(base: 2, digits: 'αβ')->caseSensitive(false)->parse('ΒΑ')->value());
    $this->assertSame(255, C::hexadecimalValue()->caseSensitive(false)->parse('FF')->value());
    $this->assertSame(PHP_INT_MIN, C::integerValue(minimum: null)->parse((string) PHP_INT_MIN)->value());
    $this->assertSame(PHP_INT_MAX, C::integerValue()->parse((string) PHP_INT_MAX)->value());
    $this->assertSame(1, C::integerValue(sign: SignPolicy::REQUIRED)->parse('+1')->value());
    $this->assertSame(0, C::integerValue(allowLeadingZeros: true)->parse('-00')->value());
    $this->assertSame(1, C::integerValue(base: 2, digits: '0+')->parse('+')->value());
});

test('typed floats keep native precision and underflow with original source text', function (): void {
    $this->assertSame(0.0, C::floatValue()->parse('1e-9999')->value());
    $this->assertSame(-1.25, C::floatValue(minimum: null)->parse('-1.25')->value());
    $this->assertSame(0.0, C::floatValue(minimum: null)->parse('-1e-9999')->value());
    $this->assertFalse(C::floatValue(allowWholeNumbers: false)->parse('1')->success);
    $this->assertSame(1.0, C::floatValue(allowWholeNumbers: false)->parse('1.')->value());
});

test('typed projections remain lazy cached replaceable and independent of commit effects', function (): void {
    $calls = $effects = 0;
    $parser = C::integerValue()->map(static function (int $value) use (&$calls): int {
        ++$calls;

        return $value + 1;
    })->onCommit(static function () use (&$effects): void {
        ++$effects;
    });
    $result = $parser->parse('12');
    $this->assertSame(0, $calls);
    $this->assertSame(0, $effects);
    $this->assertSame(13, $result->value());
    $this->assertSame(13, $result->value());
    $this->assertSame(1, $calls);
    $this->assertTrue($result->commit());
    $this->assertSame(1, $effects);
    $replaced = $parser->mapValue(static fn(ParseResult $r): string => $r->rawText())->parse('12');
    $this->assertSame('12', $replaced->value());
    $this->assertSame(1, $calls);
    $this->assertTrue(C::integerValue()->guard(
        static fn(ConcreteNode $node): bool => $node->span->end === 2,
    )->parse('12')->success);
});

test('typed parser snapshots preserve source spans values and fluent appending', function (): void {
    $parser = new IntegerValue();
    $frozen = C::of($parser)->freeze();
    $parser->map(static fn(int $value): int => $value + 100);
    $result = $frozen->matchPrefix(new ByteSource("é12\xff"), byteOffset: 2);
    $this->assertSame(12, $result->value());
    $this->assertSame('12', $result->rawText());
    $this->assertSame([2, 4], [$result->node->span->start, $result->node->span->end]);
    $this->assertTrue((new IntegerValue())->supportsSnapshotReuse());
    $this->assertTrue((new FloatValue())->supportsSnapshotReuse());
    $this->assertSame('@12', C::text('@')->integerValue()->parse('@12')->value());
    $collected = C::collect(C::integerValue(), C::floatValue())->spacing(C::text(' '))->parse('12 1.5');
    $this->assertSame([12, 1.5], $collected->value());
});
