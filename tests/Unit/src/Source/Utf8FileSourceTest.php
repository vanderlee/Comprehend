<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

uses(TestCase::class);

test('code point reads cross pages without changing byte coordinates', function (): void {
    $stream = tmpfile();
    $this->assertIsResource($stream);
    try {
        fwrite($stream, "abc\u{1f642}d");
        $source = new Utf8FileSource($stream, 4);
    } finally {
        fclose($stream);
    }

    $this->assertSame(SourceMode::UTF8, $source->mode());
    $this->assertSame(5, $source->codePointLength());
    $this->assertSame("\u{1f642}", $source->readCodePoint(3)->rawText());
    $this->assertSame(7, $source->byteOffsetForCodePointPosition(4));
    $this->assertSame(4, $source->codePointPositionForByteOffset(7));
    $this->assertFalse($source->isCodePointBoundary(4));
    $this->assertNull($source->readUnit(8));
    $source->close();
});

test('truncated utf8 is rejected and borrowed stream is restored', function (): void {
    $stream = tmpfile();
    $this->assertIsResource($stream);
    try {
        fwrite($stream, "a\xf0\x9f");
        fseek($stream, 1);
        try {
            new Utf8FileSource($stream);
            $this->fail('Expected invalid UTF-8 rejection');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('invalid sequence at byte 3', $error->getMessage());
            $this->assertSame(1, ftell($stream));
        }
    } finally {
        fclose($stream);
    }
});

test('utf8 file byte access agrees with memory at every boundary near page edges', function (): void {
    $units = "\0\u{7f}\u{80}\u{7ff}\u{800}\u{d7ff}\u{e000}\u{ffff}\u{10000}\u{10ffff}";
    foreach ([4, 5, 64, 8192] as $pageSize) {
        for ($shift = 0; $shift < 4; ++$shift) {
            $start = $pageSize - 3 + $shift;
            $bytes = str_repeat('a', $start) . $units;
            $stream = tmpfile();
            fwrite($stream, $bytes);
            $source = new Utf8FileSource($stream, $pageSize);
            fclose($stream);
            $memory = new Utf8Source($bytes);
            try {
                for ($offset = $start; $offset <= strlen($bytes); ++$offset) {
                    $boundary = $memory->isUnitBoundary($offset);
                    $this->assertSame($boundary, $source->isUnitBoundary($offset));
                    $this->assertSame($boundary, $source->isCodePointBoundary($offset));
                    if ($boundary) {
                        $expected = $memory->readUnit($offset);
                        $actual = $source->readUnit($offset);
                        $this->assertSame($expected?->end, $actual?->end);
                        $this->assertSame($expected?->rawText(), $actual?->rawText());
                    } else {
                        try {
                            $source->readUnit($offset);
                            $this->fail('Expected an interior offset rejection');
                        } catch (InvalidArgumentException $error) {
                            $this->assertSame('Byte offset is inside a UTF-8 code point', $error->getMessage());
                        }
                    }
                }
            } finally {
                $source->close();
            }
        }
    }
});

test('utf8 file access preserves range errors and prioritizes closure even at eof', function (string $method): void {
    $stream = tmpfile();
    fwrite($stream, "\u{1f642}");
    $source = new Utf8FileSource($stream, 4);
    fclose($stream);
    try {
        foreach ([-1, 5, PHP_INT_MIN, PHP_INT_MAX] as $offset) {
            try {
                $source->$method($offset);
                $this->fail('Expected invalid offset rejection');
            } catch (InvalidArgumentException $error) {
                $this->assertSame('Byte offset is outside the source', $error->getMessage());
            }
        }
        $source->close();
        foreach ([-1, 0, 1, 4, 5] as $offset) {
            try {
                $source->$method($offset);
                $this->fail('Expected closed source rejection');
            } catch (LogicException $error) {
                $this->assertSame('File source is closed', $error->getMessage());
            }
        }
    } finally {
        $source->close();
    }
})->with(['readUnit', 'readCodePoint', 'isUnitBoundary', 'isCodePointBoundary']);
