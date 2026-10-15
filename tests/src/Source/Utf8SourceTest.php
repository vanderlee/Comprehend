<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8Source;

final class Utf8SourceTest extends TestCase
{
    public function testCodePointReadsAndConversionsKeepByteOffsets(): void
    {
        $source = new Utf8Source("a\u{e9}\u{1f642}");

        $this->assertSame(SourceMode::UTF8, $source->mode());
        $this->assertSame(7, $source->byteLength());
        $this->assertSame(3, $source->codePointLength());
        $this->assertSame("\u{e9}", $source->readCodePoint(1)->rawText());
        $this->assertSame(3, $source->byteOffsetForCodePointPosition(2));
        $this->assertSame(2, $source->codePointPositionForByteOffset(3));
        $this->assertFalse($source->isUnitBoundary(2));
        $this->assertNull($source->readUnit(7));
    }

    public function testMalformedInputIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Utf8Source("\xc3");
    }

    public function testReadingInsideACodePointIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Utf8Source("\u{e9}"))->readUnit(1);
    }
}
