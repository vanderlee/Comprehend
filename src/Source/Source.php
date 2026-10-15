<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

/** Source bytes must remain stable for the lifetime of every referencing span. */
interface Source
{
    public function mode(): SourceMode;

    public function byteLength(): int;

    /** Slice the half-open byte interval [start, end), even inside a code point. */
    public function sliceBytes(int $start, int $end): string;

    public function span(int $start, int $end): Span;

    /** Recognition units are octets in byte mode and code points in UTF-8 mode. */
    public function readUnit(int $byteOffset): ?Span;

    public function isUnitBoundary(int $byteOffset): bool;
}
