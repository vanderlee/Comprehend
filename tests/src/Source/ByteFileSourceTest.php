<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\SourceMode;

final class ByteFileSourceTest extends TestCase
{
    public function testPageSizeIsValidatedBeforeOpeningInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File page size must be at least 4 bytes');

        new ByteFileSource('unused', 0);
    }

    public function testBorrowedStreamIsPreservedWhileReadsUseAnOwnedSnapshot(): void
    {
        $stream = tmpfile();
        $this->assertIsResource($stream);
        try {
            fwrite($stream, "abcd\xfff");
            fseek($stream, 2);
            $source = new ByteFileSource($stream, 4);
            $this->assertSame(2, ftell($stream));
            fseek($stream, 0);
            fwrite($stream, 'xxxxxx');

            $this->assertSame(SourceMode::BYTES, $source->mode());
            $this->assertSame("d\xfff", $source->sliceBytes(3, 6));
            $this->assertSame("\xff", $source->readUnit(4)->rawText());
            $this->assertNull($source->readUnit(6));
            $source->close();
            $this->assertIsResource($stream);
        } finally {
            fclose($stream);
        }
    }

    public function testClosedSourcesRejectEvenCachedReads(): void
    {
        $stream = tmpfile();
        $this->assertIsResource($stream);
        try {
            fwrite($stream, 'abc');
            $source = new ByteFileSource($stream);
        } finally {
            fclose($stream);
        }
        $this->assertSame('a', $source->sliceBytes(0, 1));
        $source->close();
        $source->close();
        $this->assertTrue($source->isClosed());
        $this->expectException(LogicException::class);

        $source->sliceBytes(0, 1);
    }
}
