<?php

declare(strict_types=1);

namespace Tests\Src\Source;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\CodePointSource;
use Vanderlee\Comprehend\Source\EncodedFileSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

final class CodePointSourceTest extends TestCase
{
    public function testImplementationsShareByteCoordinatesAndSourceIdentity(): void
    {
        $stream = tmpfile();
        fwrite($stream, "a\u{e9}\u{1f642}");
        $sources = [
            new Utf8Source("a\u{e9}\u{1f642}"),
            new Utf8FileSource($stream, 4),
            new EncodedFileSource($stream, 'UTF-8', 4),
        ];
        fclose($stream);

        try {
            foreach ($sources as $source) {
                $this->assertCoordinates($source);
            }
        } finally {
            $sources[1]->close();
            $sources[2]->close();
        }
    }

    private function assertCoordinates(CodePointSource $source): void
    {
        $this->assertSame(3, $source->codePointLength());
        foreach ([0, 1, 3, 7] as $position => $offset) {
            $this->assertSame($offset, $source->byteOffsetForCodePointPosition($position));
            $this->assertSame($position, $source->codePointPositionForByteOffset($offset));
            $this->assertTrue($source->isCodePointBoundary($offset));
        }
        $unit = $source->readCodePoint(3);
        $this->assertSame($source, $unit->source);
        $this->assertSame("\u{1f642}", $unit->rawText());
        $this->assertNull($source->readCodePoint(7));
        $this->assertFalse($source->isCodePointBoundary(2));
        try {
            $source->readCodePoint(2);
            $this->fail('An interior byte cannot begin a code-point read');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('inside', $error->getMessage());
        }
    }
}
