<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

use InvalidArgumentException;

/**
 * Shares stable byte storage, checked slicing and span construction for memory sources.
 * ByteSource and Utf8Source supply their own recognition-unit and boundary rules.
 */
abstract class InMemorySource implements Source
{
    protected readonly string $bytes;
    private readonly int $byteLength;

    public function __construct(string $bytes)
    {
        $this->bytes = $bytes;
        $this->byteLength = strlen($bytes);
    }

    final public function byteLength(): int
    {
        return $this->byteLength;
    }

    final public function sliceBytes(int $start, int $end): string
    {
        $this->assertByteOffset($start);
        $this->assertByteOffset($end);
        if ($end < $start) {
            throw new InvalidArgumentException('Span end must not precede its start');
        }

        return substr($this->bytes, $start, $end - $start);
    }

    protected function assertByteOffset(int $offset): void
    {
        if ($offset < 0 || $offset > $this->byteLength) {
            throw new InvalidArgumentException('Byte offset is outside the source');
        }
    }

    final public function span(int $start, int $end): Span
    {
        return new Span($this, $start, $end);
    }
}
