<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

use InvalidArgumentException;

/** Immutable half-open byte span retaining its original source. */
final class Span
{
    public function __construct(public readonly Source $source, public readonly int $start, public readonly int $end)
    {
        if ($start < 0 || $end < $start || $end > $source->byteLength()) {
            throw new InvalidArgumentException('Span must satisfy 0 <= start <= end <= source byte length');
        }
    }

    public function byteLength(): int
    {
        return $this->end - $this->start;
    }

    public function rawText(): string
    {
        return $this->source->sliceBytes($this->start, $this->end);
    }

    /** Smallest span covering both intervals, including any gap between them. */
    public function cover(self $other): self
    {
        if ($this->source !== $other->source) {
            throw new InvalidArgumentException('Cannot combine spans from different sources');
        }

        return new self($this->source, min($this->start, $other->start), max($this->end, $other->end));
    }
}
