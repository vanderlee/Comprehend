<?php

declare(strict_types=1);

namespace Tests\features\sources;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\Utf8Source;

class SourceTest extends TestCase
{
    /** @dataProvider coordinateData */
    public function testCoordinateContract($text, $start, $end, $raw, $cpStart, $cpEnd)
    {
        $source = new Utf8Source($text);
        $span = $source->span($start, $end);
        $this->assertSame($source, $span->source);
        $this->assertSame($raw, $span->rawText());
        $this->assertSame($raw, $source->sliceBytes($start, $end));
        $this->assertSame(strlen($raw), $span->byteLength());
        $this->assertSame($cpStart, $source->codePointPositionForByteOffset($start));
        $this->assertSame($cpEnd, $source->codePointPositionForByteOffset($end));
        $this->assertSame($start, $source->byteOffsetForCodePointPosition($cpStart));
        $this->assertSame($end, $source->byteOffsetForCodePointPosition($cpEnd));
    }

    public function coordinateData()
    {
        return [
            'ASCII' => ['abc', 1, 3, 'bc', 1, 3],
            'two-byte code point' => ["éa", 0, 2, "é", 0, 1],
            'after multibyte prefix' => ["éa", 2, 3, 'a', 1, 2],
            'empty at boundary' => ["éa", 2, 2, '', 1, 1],
            'empty at EOF' => ["éa", 3, 3, '', 2, 2],
            'empty source' => ['', 0, 0, '', 0, 0],
            'decomposed code points' => ["é", 0, 3, "é", 0, 2],
            'four-byte prefix' => ["😀a", 4, 5, 'a', 1, 2],
        ];
    }

    public function testAllOctetsAreAcceptedAndReadWithoutDecoding()
    {
        $bytes = implode('', array_map('chr', range(0, 255)));
        $source = new ByteSource($bytes);
        $this->assertSame(SourceMode::BYTES, $source->mode());
        $this->assertSame(256, $source->byteLength());
        $this->assertSame($bytes, $source->span(0, 256)->rawText());
        foreach (range(0, 255) as $offset) {
            $read = $source->readByte($offset);
            $this->assertSame(chr($offset), $read->rawText());
            $this->assertSame($offset + 1, $read->end);
        }
        $this->assertNull($source->readByte(256));
    }

    public function testCodePointReadsUseOriginalByteBoundaries()
    {
        $source = new Utf8Source("Aé€😀\x00");
        $this->assertSame(SourceMode::UTF8, $source->mode());
        $this->assertSame(11, $source->byteLength());
        $this->assertSame(5, $source->codePointLength());
        $boundaries = [0, 1, 3, 6, 10, 11];
        $points = ['A', "é", "€", "😀", "\x00"];
        foreach ($boundaries as $position => $offset) {
            $this->assertTrue($source->isCodePointBoundary($offset));
            $this->assertSame($offset, $source->byteOffsetForCodePointPosition($position));
            $this->assertSame($position, $source->codePointPositionForByteOffset($offset));
            if ($position < count($points)) {
                $read = $source->readCodePoint($offset);
                $this->assertSame($source, $read->source);
                $this->assertSame($points[$position], $read->rawText());
                $this->assertSame($boundaries[$position + 1], $read->end);
            }
        }
        $this->assertNull($source->readCodePoint(11));
    }

    public function testRawByteAccessMaySplitCodePoints()
    {
        $source = new Utf8Source("éa");
        $this->assertFalse($source->isCodePointBoundary(1));
        $this->assertSame("\xa9", $source->sliceBytes(1, 2));
        $this->assertSame("\xa9", $source->span(1, 2)->rawText());
        $this->assertSame('', $source->span(1, 1)->rawText());
    }

    /** @dataProvider invalidRangeData */
    public function testInvalidRangesAreRejected($start, $end, $operation)
    {
        $source = new ByteSource('abc');
        $this->expectException(InvalidArgumentException::class);
        if ($operation === 'constructor') {
            new Span($source, $start, $end);
        } else {
            $source->{$operation}($start, $end);
        }
    }

    public function invalidRangeData()
    {
        $cases = [];
        foreach ([[-1, 0], [0, -1], [2, 1], [0, 4], [4, 4], [PHP_INT_MAX, PHP_INT_MAX]] as [$start, $end]) {
            foreach (['sliceBytes', 'span', 'constructor'] as $operation) {
                $cases[] = [$start, $end, $operation];
            }
        }

        return $cases;
    }

    /** @dataProvider invalidUtf8Data */
    public function testMalformedUtf8IsRejectedAtConstruction($bytes)
    {
        // The same input remains available explicitly as uninterpreted bytes.
        $this->assertSame($bytes, (new ByteSource($bytes))->span(0, strlen($bytes))->rawText());
        $this->expectException(InvalidArgumentException::class);
        new Utf8Source($bytes);
    }

    public function invalidUtf8Data()
    {
        return [
            'Latin-1' => ["\xe9"],
            'isolated continuation' => ["\x80"],
            'truncated two-byte sequence' => ["\xc3"],
            'truncated three-byte sequence' => ["\xe2\x82"],
            'truncated four-byte sequence' => ["\xf0\x9f\x98"],
            'wrong continuation' => ["\xc3a"],
            'overlong two-byte sequence' => ["\xc0\xaf"],
            'overlong three-byte sequence' => ["\xe0��"],
            'overlong four-byte sequence' => ["\xf0���"],
            'surrogate' => ["���"],
            'above Unicode maximum' => ["����"],
            'invalid leading byte' => ["\xff"],
        ];
    }

    /** @dataProvider invalidPositionData */
    public function testInvalidReadAndConversionPositionsAreRejected($method, $position)
    {
        $source = new Utf8Source("Aé€😀\x00");
        $this->expectException(InvalidArgumentException::class);
        $source->{$method}($position);
    }

    public function invalidPositionData()
    {
        $cases = [];
        foreach ([-1, 2, 4, 5, 7, 8, 9, 12] as $offset) {
            foreach (['readCodePoint', 'codePointPositionForByteOffset'] as $method) {
                $cases[] = [$method, $offset];
            }
        }
        foreach ([-1, 12] as $offset) {
            $cases[] = ['isCodePointBoundary', $offset];
        }
        foreach ([-1, 6] as $position) {
            $cases[] = ['byteOffsetForCodePointPosition', $position];
        }

        return $cases;
    }

    /** @dataProvider invalidByteReadData */
    public function testByteReadDoesNotClampInvalidOffsets($offset)
    {
        $this->expectException(InvalidArgumentException::class);
        (new ByteSource('abc'))->readByte($offset);
    }

    public function invalidByteReadData()
    {
        return [[-1], [4]];
    }

    public function testEmptySourcesHaveValidEofSpansAndNoReadUnit()
    {
        $bytes = new ByteSource('');
        $text = new Utf8Source('');
        foreach ([$bytes, $text] as $source) {
            $this->assertSame(0, $source->byteLength());
            $this->assertSame('', $source->span(0, 0)->rawText());
            $this->assertSame(0, $source->span(0, 0)->byteLength());
        }
        $this->assertNull($bytes->readByte(0));
        $this->assertNull($text->readCodePoint(0));
        $this->assertSame(0, $text->codePointLength());
        $this->assertSame(0, $text->byteOffsetForCodePointPosition(0));
        $this->assertSame(0, $text->codePointPositionForByteOffset(0));
    }

    public function testEncodingAndRawTextDoNotDependOnAmbientSettings()
    {
        $encoding = mb_internal_encoding();
        try {
            mb_internal_encoding('ISO-8859-1');
            $bytes = "﻿" . "é\r\n\x00😀";
            $source = new Utf8Source($bytes);
            $this->assertSame($bytes, $source->span(0, strlen($bytes))->rawText());
            $this->assertSame(7, $source->codePointLength());
            $this->assertSame("﻿", $source->readCodePoint(0)->rawText());
            $this->assertSame(3, $source->byteOffsetForCodePointPosition(1));
            $this->assertSame(1, $source->codePointPositionForByteOffset(3));
            $this->expectException(InvalidArgumentException::class);
            new Utf8Source("\xe9");
        } finally {
            mb_internal_encoding($encoding);
        }
    }

    public function testSpanRetainsStableSourceAfterOriginalVariableChanges()
    {
        $input = "éa";
        $source = new Utf8Source($input);
        $span = $source->span(0, 2);
        $input = 'changed';
        unset($source);
        $this->assertSame("é", $span->rawText());
        $this->assertSame(3, $span->source->byteLength());
    }

    public function testCoverKeepsSourceIdentityAndIncludesGap()
    {
        $source = new ByteSource('abcde');
        $first = $source->span(0, 1);
        $second = $source->span(4, 5);
        $cover = $second->cover($first);
        $this->assertSame($source, $cover->source);
        $this->assertSame('abcde', $cover->rawText());
        $this->assertSame(5, $cover->byteLength());
        $this->assertSame('a', $first->rawText());
        $this->expectException(InvalidArgumentException::class);
        $first->cover((new ByteSource('abcde'))->span(0, 1));
    }
}
