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
final class Utf8FileSource extends ByteFileSource implements CodePointSource
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
        $byte = $this->readByteValue($byteOffset);

        return $byte === null || ($byte & 0xc0) !== 0x80;
    }

    public function readCodePoint(int $byteOffset): ?Span
    {
        return $this->readUnit($byteOffset);
    }

    public function readUnit(int $byteOffset): ?Span
    {
        $lead = $this->readByteValue($byteOffset);
        if ($lead === null) {
            return null;
        }
        if (($lead & 0xc0) === 0x80) {
            throw new InvalidArgumentException('Byte offset is inside a UTF-8 code point');
        }
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
        $offset = 0;
        while ($offset < $this->byteLength()) {
            $end = min($offset + 8192, $this->byteLength());
            // Peek beyond the proposed end so a valid code point is never split.
            // Three retreats suffice for UTF-8; malformed runs go to scalar validation.
            $chunk = $this->sliceBytes($offset, min($end + 1, $this->byteLength()));
            $length = $end - $offset;
            for ($retreat = 0; $retreat < 3 && $length < strlen($chunk); ++$retreat) {
                if ((ord($chunk[$length]) & 0xc0) !== 0x80) {
                    break;
                }
                --$length;
            }
            $chunk = substr($chunk, 0, $length);
            $this->codePoints += mb_check_encoding($chunk, 'UTF-8')
                ? mb_strlen($chunk, 'UTF-8')
                : $this->validateChunk($chunk, $offset);
            $offset += $length;
        }
    }

    /** Scalar fallback retains the original first-invalid-byte and truncated-EOF diagnostics. */
    private function validateChunk(string $chunk, int $offset): int
    {
        $count = 0;
        $remaining = 0;
        $minimum = 0x80;
        $maximum = 0xbf;
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
                    ++$count;
                }
            } elseif ($byte < 0x80) {
                ++$count;
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
        if ($remaining !== 0) {
            $this->invalidUtf8($offset + $indexMax);
        }

        return $count;
    }

    private function invalidUtf8(int $offset): never
    {
        throw new InvalidArgumentException('File source must contain valid UTF-8; invalid sequence at byte ' . $offset);
    }
}
