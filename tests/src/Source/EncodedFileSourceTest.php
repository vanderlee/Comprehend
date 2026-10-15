<?php

declare(strict_types=1);

namespace Tests\src\Source;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Source\EncodedFileSource;

class EncodedFileSourceTest extends TestCase
{
    /** @dataProvider encodings */
    public function testExplicitEncodingsProduceStableUtf8Spans(string $encoding, string $text): void
    {
        $stream = tmpfile();
        $bytes = mb_convert_encoding($text, $encoding, 'UTF-8');
        fwrite($stream, $bytes);
        fseek($stream, 1);
        try {
            $source = new EncodedFileSource($stream, $encoding, 4);
            $this->assertSame(1, ftell($stream));
            rewind($stream);
            fwrite($stream, 'changed');
            $result = C::text($text)->named('text')->parse($source);
            $this->assertTrue($result->success);
            $this->assertSame($text, $result->rawText());
            $this->assertSame(strlen($text), $result->consumedByteLength);
            $this->assertSame(strlen($bytes), $source->originalByteLength);
            $this->assertSame($source, $result->span->source);
            $this->assertSame($source, $source->readUnit(0)->source);
            $this->assertSame(mb_strlen($text, 'UTF-8'), $source->codePointLength());
            $this->assertSame(0, $source->codePointPositionForByteOffset(0));
            $this->assertSame(0, $source->byteOffsetForCodePointPosition(0));
            $source->close();
            $this->assertTrue($source->isClosed());
            $this->expectException(LogicException::class);
            $result->rawText();
        } finally {
            fclose($stream);
        }
    }

    public function encodings(): array
    {
        return [
            ['Windows-1252', 'café €'],
            ['UTF-16LE', 'é🙂'],
            ['UTF-16BE', 'é🙂'],
            ['SJIS', '日本'],
            ['ISO-2022-JP', '日本'],
        ];
    }

    public function testMalformedInputIsRejectedWithoutSubstitutionOrMovingBorrowedStream(): void
    {
        $stream = tmpfile();
        fwrite($stream, "\x00");
        fseek($stream, 1);
        try {
            try {
                new EncodedFileSource($stream, 'UTF-16LE');
                $this->fail('Expected malformed encoding error');
            } catch (InvalidArgumentException $error) {
                $this->assertStringContainsString('not valid', $error->getMessage());
                $this->assertSame(1, ftell($stream));
            }
        } finally {
            fclose($stream);
        }
    }

    public function testUnknownEncodingIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EncodedFileSource('unused', 'made-up-encoding');
    }

    public function testTransferEncodingsAreNotCharacterEncodings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EncodedFileSource('unused', 'base64');
    }
}
