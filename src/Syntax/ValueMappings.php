<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\ParseResult;

/**
 * Analysis-only signatures for the facade's magic mapping operations.
 * These methods are implemented by Catalog, never by a second parser runtime.
 * @template T = mixed
 * @internal
 */
abstract class ValueMappings
{
    /**
     * Extraction must be installed on the runtime vocabulary before using these operations.
     * @template U
     * @param callable(T): U $mapper
     * @return Comprehend<U>
     */
    abstract public function map(callable $mapper): Comprehend;

    /**
     * @template U
     * @param callable(string): U $mapper
     * @return Comprehend<U>
     */
    abstract public function mapText(callable $mapper): Comprehend;

    /**
     * @template U
     * @template V
     * @param Comprehend<U>|Parser|string $next
     * @param callable(T, U): V $combine
     * @return Comprehend<V>
     */
    abstract public function mapWith(Comprehend|Parser|string $next, callable $combine): Comprehend;

    /**
     * @template U
     * @param Comprehend<U>|Parser|string $step
     * @param callable(T, U): T $combine
     * @return Comprehend<T>
     */
    abstract public function foldLeft(
        Comprehend|Parser|string $step,
        callable $combine,
        int $min = 0,
        ?int $max = null,
    ): Comprehend;

    /** @return Comprehend<list<T>> */
    abstract public function manyValues(): Comprehend;

    /** @return Comprehend<non-empty-list<T>> */
    abstract public function someValues(): Comprehend;

    /** @return Comprehend<list<T>> */
    abstract public function repeatValues(int $min = 0, ?int $max = null): Comprehend;

    /**
     * @template U = null
     * @param U $default
     * @return Comprehend<T|U>
     */
    abstract public function optionalValue(mixed $default = null): Comprehend;

    /** @return Comprehend<list<mixed>> */
    abstract public function collect(Comprehend|Parser|string ...$next): Comprehend;

    abstract public function valueChoice(Comprehend|Parser|string ...$next): Comprehend;

    /**
     * Appending a factory to an existing expression produces a raw sequence value.
     * @param array<array-key, Comprehend|Parser|string> $fields
     * @return Comprehend<string>
     */
    abstract public function record(array $fields): Comprehend;

    /**
     * @template U
     * @param callable(ParseResult): U $mapper
     * @return Comprehend<U>
     */
    abstract public function mapValue(callable $mapper): Comprehend;
}
