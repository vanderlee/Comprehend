<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Provides iterable and array-style child storage for composite parsers. Choice and Sequence
 * share this typed Parser container for grammar construction and traversal.
 *
 * @author Martijn
 * @implements IteratorAggregate<array-key, Parser>
 * @implements ArrayAccess<array-key, Parser>
 */
abstract class IterableParser extends Parser implements IteratorAggregate, ArrayAccess
{
    /**
     * @var Parser[]
     */
    protected $parsers = [];

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parsers = array_map($context->copy(...), $this->parsers);
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return array_values($this->parsers);
    }

    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->parsers);
    }

    /** @param mixed $value Validated at the public array-access boundary. */
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
