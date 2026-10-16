<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('code point reads and conversions keep byte offsets', function (): void {
    $source = new Utf8Source("a\u{e9}\u{1f642}");

    $this->assertSame(SourceMode::UTF8, $source->mode());
    $this->assertSame(7, $source->byteLength());
    $this->assertSame(3, $source->codePointLength());
    $this->assertSame("\u{e9}", $source->readCodePoint(1)->rawText());
    $this->assertSame(3, $source->byteOffsetForCodePointPosition(2));
    $this->assertSame(2, $source->codePointPositionForByteOffset(3));
    $this->assertFalse($source->isUnitBoundary(2));
    $this->assertNull($source->readUnit(7));
});

test('malformed input is rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);

    new Utf8Source("\xc3");
});

test('reading inside a code point is rejected', function (): void {
    $this->expectException(InvalidArgumentException::class);

    (new Utf8Source("\u{e9}"))->readUnit(1);
});

test('unit and code point boundary APIs agree on every utf8 byte and eof', function (): void {
    $source = new Utf8Source("a\u{e9}\u{20ac}\u{1f642}");
    $boundaries = [0, 1, 3, 6, 10];
    for ($offset = 0; $offset <= 10; ++$offset) {
        $expected = in_array($offset, $boundaries, true);
        $this->assertSame($expected, $source->isUnitBoundary($offset));
        $this->assertSame($expected, $source->isCodePointBoundary($offset));
    }
    $this->assertTrue((new Utf8Source(''))->isUnitBoundary(0));
    $this->assertTrue((new Utf8Source(''))->isCodePointBoundary(0));
});

test('both boundary APIs reject out of range byte positions with the original error', function (
    string $method,
    int $offset,
): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Byte offset is outside the source');
    (new Utf8Source("a\u{e9}"))->$method($offset);
})->with(['isUnitBoundary', 'isCodePointBoundary'])->with([-1, 4, PHP_INT_MIN, PHP_INT_MAX]);
