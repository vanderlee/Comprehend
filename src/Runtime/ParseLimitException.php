<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use RuntimeException;

/**
 * Signals cooperative parse-budget exhaustion separately from grammar mismatch. Carries the
 * exhausted limit and source position so callers can distinguish resource limits from rejected
 * input.
 */
final class ParseLimitException extends RuntimeException
{
    /**
     * @param list<string> $rulePath
     */
    public function __construct(
        public readonly string $limit,
        public readonly int $byteOffset,
        public readonly array $rulePath,
        public readonly int $maximum,
    ) {
        parent::__construct(
            'Parse ' . $limit . ' limit ' . $maximum . ' exceeded at byte ' . $byteOffset
            . ': ' . implode(' -> ', $rulePath),
        );
    }
}
