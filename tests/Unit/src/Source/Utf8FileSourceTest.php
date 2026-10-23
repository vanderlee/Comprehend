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

test('bounded utf8 validation counts complete chunks and preserves byte conversions', function (): void {
    $units = ["\0", "\u{7f}", "\u{80}", "\u{7ff}", "\u{800}", "\u{d7ff}",
        "\u{e000}", "\u{ffff}", "\u{10000}", "\u{10ffff}"];
    foreach ([4, 8192] as $pageSize) {
        foreach ([0, 1, 8189, 8190, 8191, 8192, 8193, 16383, 16384] as $padding) {
            $bytes = str_repeat('a', $padding) . implode('', $units) . str_repeat("\u{1f642}", 2100);
            $memory = new Utf8Source($bytes);
            $stream = tmpfile();
            fwrite($stream, $bytes);
            fseek($stream, 1);
            try {
                $source = new Utf8FileSource($stream, $pageSize);
                $this->assertSame(1, ftell($stream));
                $this->assertSame($memory->codePointLength(), $source->codePointLength());
                $this->assertSame($bytes, $source->sliceBytes(0, strlen($bytes)));
                $position = $padding;
                $offset = $padding;
                foreach ($units as $unit) {
                    $this->assertSame($unit, $source->readUnit($offset)->rawText());
                    ++$position;
                    $offset += strlen($unit);
                }
                $this->assertSame($offset, $source->byteOffsetForCodePointPosition($position));
                $this->assertSame($position, $source->codePointPositionForByteOffset($offset));
                $source->close();
            } finally {
                fclose($stream);
            }
        }
    }
});

test('rejected validation chunks retain exact invalid byte diagnostics across boundaries', function (): void {
    $cases = [
        ["\x80", 0], ["\xc0\xaf", 0], ["\xc1\xbf", 0], ["\xc2!", 1], ["\xc2", 1],
        ["\xe0\x80\x80", 1], ["\xed\xa0\x80", 1], ["\xf0\x80\x80\x80", 1],
        ["\xf4\x90\x80\x80", 1], ["\xf5\x80\x80\x80", 0], ["\xef\xbf", 2],
        ["\xf4\x8f\xbf", 3], ["\xe2\x82!", 2], [str_repeat("\x80", 20), 0],
    ];
    foreach ([4, 8192] as $pageSize) {
        foreach ([0, 1, 8189, 8190, 8191, 8192, 8193, 16383, 16384] as $padding) {
            foreach ($cases as [$invalid, $badByte]) {
                $stream = tmpfile();
                $prefix = $padding === 16384
                    ? str_repeat("\u{e9}\u{1f642}", 2730) . 'aaaa' : str_repeat('a', $padding);
                fwrite($stream, $prefix . $invalid);
                fseek($stream, 1);
                try {
                    new Utf8FileSource($stream, $pageSize);
                    $this->fail('Expected malformed UTF-8 rejection');
                } catch (InvalidArgumentException $error) {
                    $this->assertSame(
                        'File source must contain valid UTF-8; invalid sequence at byte ' . ($padding + $badByte),
                        $error->getMessage(),
                    );
                    $this->assertSame(1, ftell($stream));
                    $this->assertIsResource($stream);
                } finally {
                    fclose($stream);
                }
            }
        }
    }
});

test('utf8 validation keeps temporary memory bounded as files grow', function (): void {
    foreach ([1024 * 1024, 9 * 1024 * 1024] as $size) {
        $stream = tmpfile();
        $block = str_repeat("a\u{e9}\u{20ac}\u{1f642}", 800);
        for ($offset = 0; $offset < $size; $offset += strlen($block)) {
            fwrite($stream, $block);
        }
        $blocks = intdiv($size + strlen($block) - 1, strlen($block));
        try {
            memory_reset_peak_usage();
            $before = memory_get_usage();
            $source = new Utf8FileSource($stream);
            $this->assertLessThan(256 * 1024, memory_get_peak_usage() - $before);
            $this->assertSame($blocks * 3200, $source->codePointLength());
            $source->close();
        } finally {
            fclose($stream);
        }
    }
});
