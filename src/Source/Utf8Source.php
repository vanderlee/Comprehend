<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

use InvalidArgumentException;

/**
 * Provides validated in-memory UTF-8 with explicit code-point access and conversions. Extends
 * InMemorySource while keeping parser positions and spans in canonical byte coordinates.
 */
final class Utf8Source extends InMemorySource implements CodePointSource
{
    private readonly int $codePointLength;

    public function __construct(string $bytes)
    {
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            throw new InvalidArgumentException('Source must contain valid UTF-8');
        }
        parent::__construct($bytes);
        $this->codePointLength = mb_strlen($bytes, 'UTF-8');
    }

    public function mode(): SourceMode
    {
        return SourceMode::UTF8;
    }

    public function codePointLength(): int
    {
        return $this->codePointLength;
    }

    public function readUnit(int $byteOffset): ?Span
    {
        return $this->readCodePoint($byteOffset);
    }

    /** Read one code point; null denotes EOF. The span's end is the next boundary. */
    public function readCodePoint(int $byteOffset): ?Span
    {
        $this->assertCodePointBoundary($byteOffset);
        if ($byteOffset === $this->byteLength()) {
            return null;
        }
        // Validation guarantees a complete sequence beginning at this boundary.
        $lead = ord($this->bytes[$byteOffset]);
        $length = match (true) {
            $lead < 0x80 => 1,
            $lead < 0xe0 => 2,
            $lead < 0xf0 => 3,
            default => 4,
        };

        return $this->span($byteOffset, $byteOffset + $length);
    }

    private function assertCodePointBoundary(int $byteOffset): void
    {
        if (!$this->isUnitBoundary($byteOffset)) {
            throw new InvalidArgumentException('Byte offset is inside a UTF-8 code point');
        }
    }

    /** Invalid/out-of-range positions throw; an interior byte returns false. */
    public function isCodePointBoundary(int $byteOffset): bool
    {
        return $this->isUnitBoundary($byteOffset);
    }

    public function isUnitBoundary(int $byteOffset): bool
    {
        // Keep repeated parser boundary checks local to the immutable buffer.
        $length = strlen($this->bytes);
        if ($byteOffset < 0 || $byteOffset > $length) {
            throw new InvalidArgumentException('Byte offset is outside the source');
        }

        return $byteOffset === $length || (ord($this->bytes[$byteOffset]) & 0xc0) !== 0x80;
    }

    public function byteOffsetForCodePointPosition(int $position): int
    {
        if ($position < 0 || $position > $this->codePointLength) {
            throw new InvalidArgumentException('Code-point position is outside the source');
        }

        return strlen(mb_substr($this->bytes, 0, $position, 'UTF-8'));
    }

    public function codePointPositionForByteOffset(int $byteOffset): int
    {
        $this->assertCodePointBoundary($byteOffset);

        return mb_strlen(substr($this->bytes, 0, $byteOffset), 'UTF-8');
    }
}
