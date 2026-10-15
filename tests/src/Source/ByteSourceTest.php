<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\SourceMode;

final class ByteSourceTest extends TestCase
{
    public function testArbitraryOctetsAreReadWithoutDecoding(): void
    {
        $source = new ByteSource("\x00\xff");

        $this->assertSame(SourceMode::BYTES, $source->mode());
        $this->assertSame(2, $source->byteLength());
        $this->assertSame("\x00", $source->readUnit(0)->rawText());
        $this->assertSame("\xff", $source->readByte(1)->rawText());
        $this->assertTrue($source->isUnitBoundary(1));
        $this->assertNull($source->readByte(2));
    }

    public function testOutOfRangeReadsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ByteSource('a'))->readByte(2);
    }
}
