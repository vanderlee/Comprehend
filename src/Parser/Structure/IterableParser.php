<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Abstract class indicating a parser that consists of multiple parsers and can be accessed as an array.
 *
 * @author Martijn
 */
abstract class IterableParser extends Parser implements IteratorAggregate, ArrayAccess
{
    /**
     * @var Parser[]
     */
    protected $parsers = [];

    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->parsers);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof Parser) {
            throw new \InvalidArgumentException('Children must be Parser instances');
        }
        if ($offset === null) {
            $this->parsers[] = $value;
        } else {
            $this->parsers[$offset] = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->parsers[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->parsers[$offset]);
    }

    public function offsetGet(mixed $offset): ?Parser
    {
        return $this->parsers[$offset] ?? null;
    }
}
