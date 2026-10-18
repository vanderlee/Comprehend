<?php

declare(strict_types=1);

namespace Tests\Unit\src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Span;

abstract class SpanTest extends TestCase
{
    public static function invalidRanges(): array
    {
        return [[-1, 0], [2, 1], [0, 4]];
    }
}

uses(SpanTest::class);

test('cover includes the gap and retains the original source', function (): void {
    $source = new ByteSource('abcde');
    $covered = (new Span($source, 0, 1))->cover(new Span($source, 3, 5));

    $this->assertSame($source, $covered->source);
    $this->assertSame(5, $covered->byteLength());
    $this->assertSame('abcde', $covered->rawText());
});

test('cover rejects equal text from different sources', function (): void {
    $span = (new ByteSource('a'))->span(0, 1);
    $this->expectException(InvalidArgumentException::class);

    $span->cover((new ByteSource('a'))->span(0, 1));
});

test('cover reuses a containing immutable interval', function (): void {
    $source = new ByteSource('abcde');
    $outer = $source->span(0, 5);
    $inner = $source->span(1, 3);

    $this->assertSame($outer, $outer->cover($inner));
    $this->assertSame($outer, $inner->cover($outer));
    $this->assertSame($outer, $outer->cover($source->span(0, 5)));
    $this->assertSame($outer, $outer->cover($source->span(5, 5)));
});

test('invalid ranges are rejected', function (int $start, int $end): void {
    $this->expectException(InvalidArgumentException::class);

    new Span(new ByteSource('abc'), $start, $end);
})->with(SpanTest::invalidRanges());
