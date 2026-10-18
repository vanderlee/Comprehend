<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

/** Validated UTF-8 access and conversions; all offsets and spans remain bytes. */
interface CodePointSource extends Source
{
    public function codePointLength(): int;

    /** Read one code point at a byte boundary; null denotes EOF. */
    public function readCodePoint(int $byteOffset): ?Span;

    /** Out-of-range offsets throw; an interior byte returns false. */
    public function isCodePointBoundary(int $byteOffset): bool;

    public function byteOffsetForCodePointPosition(int $position): int;

    public function codePointPositionForByteOffset(int $byteOffset): int;
}
