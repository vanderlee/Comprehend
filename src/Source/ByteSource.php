<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

/** Arbitrary octets, with no decoding or implicit character conversions. */
final class ByteSource extends InMemorySource
{
    public function mode(): SourceMode
    {
        return SourceMode::BYTES;
    }

    /** Read one octet; null denotes EOF. The span's end is the next byte offset. */
    public function readByte(int $byteOffset): ?Span
    {
        $this->assertByteOffset($byteOffset);

        return $byteOffset === $this->byteLength()
            ? null
            : $this->span($byteOffset, $byteOffset + 1);
    }
}
