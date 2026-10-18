<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

/** Opts in the consuming class only; eligible subclasses must use the trait again. */
trait ExactClassSnapshotReuseTrait
{
    public function supportsSnapshotReuse(): bool
    {
        return static::class === self::class;
    }
}
