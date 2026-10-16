<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Utf8FileSource;

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
