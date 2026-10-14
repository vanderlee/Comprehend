<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use RuntimeException;

/** Budget exhaustion is distinct from ordinary grammar failure. */
final class ParseLimitException extends RuntimeException
{
    public function __construct(
        public readonly string $limit,
        public readonly int $byteOffset,
        public readonly array $rulePath,
        public readonly int $maximum
    ) {
        parent::__construct(
            'Parse ' . $limit . ' limit ' . $maximum . ' exceeded at byte ' . $byteOffset
            . ': ' . implode(' -> ', $rulePath)
        );
    }
}
