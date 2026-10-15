<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

use InvalidArgumentException;
use RuntimeException;

/** Explicit transcoding into a stable UTF-8 disk snapshot; offsets refer to UTF-8. */
final class EncodedFileSource implements Source
{
    private readonly Utf8FileSource $source;
    public readonly string $inputEncoding;
    public readonly int $originalByteLength;

    /** Conversion validates/allocates the complete input; subsequent reads use bounded pages. */
    public function __construct(mixed $input, string $encoding, int $pageSize = 8192)
    {
        $this->inputEncoding = self::normalizeEncoding($encoding);
        $original = new ByteFileSource($input, $pageSize);
        try {
            $this->originalByteLength = $original->byteLength();
            $bytes = $original->sliceBytes(0, $original->byteLength());
        } finally {
            $original->close();
        }
        if (!mb_check_encoding($bytes, $this->inputEncoding)) {
            throw new InvalidArgumentException('File is not valid ' . $this->inputEncoding);
        }
        $utf8 = mb_convert_encoding($bytes, 'UTF-8', $this->inputEncoding);
        if ($utf8 === false) {
            throw new RuntimeException('Cannot transcode file source to UTF-8');
        }
        $stream = tmpfile();
        if ($stream === false) {
            throw new RuntimeException('Cannot create transcoded source snapshot');
        }
        try {
            $offsetMax = strlen($utf8);
            for ($offset = 0; $offset < $offsetMax; $offset += $written) {
                $written = fwrite($stream, substr($utf8, $offset, $pageSize));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Cannot write transcoded source snapshot');
                }
            }
            $this->source = new Utf8FileSource($stream, $pageSize);
        } finally {
            fclose($stream);
        }
    }

    private static function normalizeEncoding(string $requested): string
    {
        foreach (mb_list_encodings() as $encoding) {
            foreach ([$encoding, ...mb_encoding_aliases($encoding)] as $alias) {
                if (strcasecmp($requested, $alias) !== 0) {
                    continue;
                }
                if (
                    in_array(
                        $encoding,
                        ['BASE64', 'UUENCODE', 'HTML-ENTITIES', 'Quoted-Printable', '7bit', '8bit'],
                        true,
                    )
                ) {
                    throw new InvalidArgumentException('A character encoding is required');
                }

                return $encoding;
            }
        }
        throw new InvalidArgumentException('Unsupported file encoding: ' . $requested);
    }

    public function mode(): SourceMode
    {
        return SourceMode::UTF8;
    }

    public function byteLength(): int
    {
        return $this->source->byteLength();
    }

    public function sliceBytes(int $start, int $end): string
    {
        return $this->source->sliceBytes($start, $end);
    }

    public function span(int $start, int $end): Span
    {
        return new Span($this, $start, $end);
    }

    public function readUnit(int $byteOffset): ?Span
    {
        $unit = $this->source->readUnit($byteOffset);

        return $unit === null ? null : $this->span($unit->start, $unit->end);
    }

    public function isUnitBoundary(int $byteOffset): bool
    {
        return $this->source->isUnitBoundary($byteOffset);
    }

    public function readCodePoint(int $byteOffset): ?Span
    {
        return $this->readUnit($byteOffset);
    }

    public function isCodePointBoundary(int $byteOffset): bool
    {
        return $this->isUnitBoundary($byteOffset);
    }

    public function codePointLength(): int
    {
        return $this->source->codePointLength();
    }

    public function byteOffsetForCodePointPosition(int $position): int
    {
        return $this->source->byteOffsetForCodePointPosition($position);
    }

    public function codePointPositionForByteOffset(int $byteOffset): int
    {
        return $this->source->codePointPositionForByteOffset($byteOffset);
    }

    public function isClosed(): bool
    {
        return $this->source->isClosed();
    }

    public function close(): void
    {
        $this->source->close();
    }

    private function __clone() {}
}
