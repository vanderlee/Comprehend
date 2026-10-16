<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Binding;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * PHPStan signatures for the opt-in Binding vocabulary.
 * @template T = mixed
 * @method static C collect(C|Parser|string ...$parsers)
 * @method static C valueChoice(C|Parser|string ...$parsers)
 * @method static C repeatValues(mixed ...$arguments)
 * @method static C manyValues(mixed ...$arguments)
 * @method static C someValues(mixed ...$arguments)
 * @method static C optionalValue(mixed ...$arguments)
 * @method static C separatedBy(mixed ...$arguments)
 * @method C<T> thenIgnore(C|Parser|string $next)
 * @method C ignoreThen(C|Parser|string $next)
 * @method C<T> between(C|Parser|string $open, C|Parser|string $close)
 * @internal
 */
abstract class Authoring
{
    /**
     * @template U
     * @param callable(T): U $mapper
     * @return C<U>
     */
    abstract public function map(callable $mapper): C;

    /**
     * @template U
     * @param callable(string): U $mapper
     * @return C<U>
     */
    abstract public function mapText(callable $mapper): C;
}
