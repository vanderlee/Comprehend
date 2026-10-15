<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Span;

final class SpanTest extends TestCase
{
    public function testCoverIncludesTheGapAndRetainsTheOriginalSource(): void
    {
        $source = new ByteSource('abcde');
        $covered = (new Span($source, 0, 1))->cover(new Span($source, 3, 5));

        $this->assertSame($source, $covered->source);
        $this->assertSame(5, $covered->byteLength());
        $this->assertSame('abcde', $covered->rawText());
    }

    public function testCoverRejectsEqualTextFromDifferentSources(): void
    {
        $span = (new ByteSource('a'))->span(0, 1);
        $this->expectException(InvalidArgumentException::class);

        $span->cover((new ByteSource('a'))->span(0, 1));
    }

    /** @dataProvider invalidRanges */
    public function testInvalidRangesAreRejected(int $start, int $end): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Span(new ByteSource('abc'), $start, $end);
    }

    public static function invalidRanges(): array
    {
        return [[-1, 0], [2, 1], [0, 4]];
    }
}
