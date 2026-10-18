<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

/**
 * Defines stable byte access and recognition-unit boundaries for every parser input. Span and
 * ParseSession depend on source bytes remaining unchanged for the lifetime of retained results.
 */
interface Source
{
    public function mode(): SourceMode;

    public function byteLength(): int;

    /** Slice the half-open byte interval [start, end), even inside a code point. */
    public function sliceBytes(int $start, int $end): string;

    /** Bind a half-open byte interval to this source; unit-boundary checks belong to recognition. */
    public function span(int $start, int $end): Span;

    /** Recognition units are octets in byte mode and code points in UTF-8 mode. */
    public function readUnit(int $byteOffset): ?Span;

    public function isUnitBoundary(int $byteOffset): bool;
}
