<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Extraction\PHPStan;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * PHPStan signatures for the opt-in Extraction vocabulary.
 * @template T = mixed
 * @method static C collect(C|Parser|string ...$parsers)
 * @method static C valueChoice(C|Parser|string ...$parsers)
 * @method static C repeatValues(mixed ...$arguments)
 * @method static C manyValues(mixed ...$arguments)
 * @method static C someValues(mixed ...$arguments)
 * @method static C optionalValue(mixed ...$arguments)
 * @internal
 */
abstract class Authoring
{
    /**
     * @param array<array-key, C|Parser|string> $fields
     * @return C<array<array-key, mixed>>
     */
    abstract public static function record(array $fields): C;

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
