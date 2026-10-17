<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

use InvalidArgumentException;

/**
 * Retains a source-bound half-open byte interval for exact text access. Trees and results use
 * spans to preserve original input independently of semantic transformations.
 */
final class Span
{
    /** @internal Check a byte position without allocating a discarded zero-width span. */
    public static function assertByteOffset(Source $source, int $offset): void
    {
        if ($offset < 0 || $offset > $source->byteLength()) {
            throw new InvalidArgumentException('Span must satisfy 0 <= start <= end <= source byte length');
        }
    }

    public function __construct(public readonly Source $source, public readonly int $start, public readonly int $end)
    {
        if (
            $start < 0
            || $end < $start
            || $end > $source->byteLength()
        ) {
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
        if ($this->start <= $other->start && $this->end >= $other->end) {
            return $this;
        }
        if ($other->start <= $this->start && $other->end >= $this->end) {
            return $other;
        }

        return new self(
            $this->source,
            min($this->start, $other->start),
            max($this->end, $other->end),
        );
    }
}
