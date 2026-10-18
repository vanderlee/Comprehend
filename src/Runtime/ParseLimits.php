<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use InvalidArgumentException;

/**
 * Defines optional depth and work budgets for ParseSession. Null disables a budget; work
 * accounts for parser entries, source-unit reads and regex subject bytes. The regex cache cap
 * bounds retained subject bytes rather than recognition work; zero disables subject caching.
 */
final readonly class ParseLimits
{
    public function __construct(
        public ?int $maxDepth = null,
        public ?int $maxWork = null,
        public int $maxRegexSubjectBytes = 8388608,
    ) {
        if (($maxDepth !== null && $maxDepth < 1) || ($maxWork !== null && $maxWork < 1)) {
            throw new InvalidArgumentException('Parse limits must be positive or null');
        }
        if ($maxRegexSubjectBytes < 0) {
            throw new InvalidArgumentException('Regex subject cache size must not be negative');
        }
    }
}
