<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

/**
 * Provides in-memory arbitrary octets without decoding. Specializes InMemorySource so every
 * byte is a recognition unit and every valid byte offset is a unit boundary.
 */
final class ByteSource extends InMemorySource
{
    public function mode(): SourceMode
    {
        return SourceMode::BYTES;
    }

    public function readUnit(int $byteOffset): ?Span
    {
        return $this->readByte($byteOffset);
    }

    /** Read one octet; null denotes EOF. The span's end is the next byte offset. */
    public function readByte(int $byteOffset): ?Span
    {
        $this->assertByteOffset($byteOffset);

        return $byteOffset === $this->byteLength() ? null : $this->span($byteOffset, $byteOffset + 1);
    }

    public function isUnitBoundary(int $byteOffset): bool
    {
        $this->assertByteOffset($byteOffset);

        return true;
    }
}
