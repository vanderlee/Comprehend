<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

use InvalidArgumentException;
use LogicException;

/**
 * Provides validated UTF-8 code-point access over ByteFileSource's stable disk snapshot.
 * Validates incrementally and keeps canonical byte offsets while offering explicit code-point
 * conversions.
 */
final class Utf8FileSource extends ByteFileSource
{
    private int $codePoints = 0;

    public function mode(): SourceMode
    {
        return SourceMode::UTF8;
    }

    public function codePointLength(): int
    {
        return $this->codePoints;
    }

    public function isCodePointBoundary(int $byteOffset): bool
    {
        return $this->isUnitBoundary($byteOffset);
    }

    public function isUnitBoundary(int $byteOffset): bool
    {
        $this->assertReadableOffset($byteOffset);
        if ($byteOffset === $this->byteLength()) {
            return true;
        }
        $byte = ord($this->sliceBytes($byteOffset, $byteOffset + 1));

        return ($byte & 0xc0) !== 0x80;
    }

    public function readCodePoint(int $byteOffset): ?Span
    {
        return $this->readUnit($byteOffset);
    }

    public function readUnit(int $byteOffset): ?Span
    {
        if (!$this->isUnitBoundary($byteOffset)) {
            throw new InvalidArgumentException('Byte offset is inside a UTF-8 code point');
        }
        if ($byteOffset === $this->byteLength()) {
            return null;
        }
        $lead = ord($this->sliceBytes($byteOffset, $byteOffset + 1));
        $length = match (true) {
            $lead < 0x80 => 1,
            $lead < 0xe0 => 2,
            $lead < 0xf0 => 3,
            default => 4,
        };

        return $this->span($byteOffset, $byteOffset + $length);
    }

    public function byteOffsetForCodePointPosition(int $position): int
    {
        $this->assertReadableOffset(0);
        if ($position < 0 || $position > $this->codePoints) {
            throw new InvalidArgumentException('Code-point position is outside the source');
        }
        $offset = 0;
        for ($index = 0; $index < $position; ++$index) {
            $unit = $this->readUnit($offset);
            if ($unit === null) {
                throw new LogicException('Unexpected end of UTF-8 source');
            }
            $offset = $unit->end;
        }

        return $offset;
    }

    public function codePointPositionForByteOffset(int $byteOffset): int
    {
        if (!$this->isUnitBoundary($byteOffset)) {
            throw new InvalidArgumentException('Byte offset is inside a UTF-8 code point');
        }
        $position = 0;
        $offset = 0;
        while ($offset < $byteOffset) {
            $unit = $this->readUnit($offset);
            if ($unit === null) {
                throw new LogicException('Unexpected end of UTF-8 source');
            }
            $offset = $unit->end;
            ++$position;
        }

        return $position;
    }

    protected function validateContent(): void
    {
        $remaining = 0;
        $minimum = 0x80;
        $maximum = 0xbf;
        for ($offset = 0; $offset < $this->byteLength(); $offset += 8192) {
            $chunk = $this->sliceBytes($offset, min($offset + 8192, $this->byteLength()));
            $indexMax = strlen($chunk);
            for ($index = 0; $index < $indexMax; ++$index) {
                $byte = ord($chunk[$index]);
                if ($remaining > 0) {
                    if ($byte < $minimum || $byte > $maximum) {
                        $this->invalidUtf8($offset + $index);
                    }
                    --$remaining;
                    $minimum = 0x80;
                    $maximum = 0xbf;
                    if ($remaining === 0) {
                        ++$this->codePoints;
                    }
                } elseif ($byte < 0x80) {
                    ++$this->codePoints;
                } elseif ($byte >= 0xc2 && $byte <= 0xdf) {
                    $remaining = 1;
                } elseif ($byte >= 0xe0 && $byte <= 0xef) {
                    $remaining = 2;
                    $minimum = $byte === 0xe0 ? 0xa0 : 0x80;
                    $maximum = $byte === 0xed ? 0x9f : 0xbf;
                } elseif ($byte >= 0xf0 && $byte <= 0xf4) {
                    $remaining = 3;
                    $minimum = $byte === 0xf0 ? 0x90 : 0x80;
                    $maximum = $byte === 0xf4 ? 0x8f : 0xbf;
                } else {
                    $this->invalidUtf8($offset + $index);
                }
            }
        }
        if ($remaining !== 0) {
            $this->invalidUtf8($this->byteLength());
        }
    }

    private function invalidUtf8(int $offset): never
    {
        throw new InvalidArgumentException('File source must contain valid UTF-8; invalid sequence at byte ' . $offset);
    }
}
