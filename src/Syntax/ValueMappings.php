<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Vanderlee\Comprehend\Comprehend;
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
     * @param callable(ParseResult): U $mapper
     * @return Comprehend<U>
     */
    abstract public function mapValue(callable $mapper): Comprehend;
}
