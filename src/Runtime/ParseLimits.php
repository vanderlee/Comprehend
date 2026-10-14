<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use InvalidArgumentException;

/** Null disables a limit; work counts parser entries, unit reads and regex subject bytes. */
final readonly class ParseLimits
{
    public function __construct(public ?int $maxDepth = null, public ?int $maxWork = null)
    {
        if (($maxDepth !== null && $maxDepth < 1) || ($maxWork !== null && $maxWork < 1)) {
            throw new InvalidArgumentException('Parse limits must be positive or null');
        }
    }
}
