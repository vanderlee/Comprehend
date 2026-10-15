<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

/** Defaults for locally valid parsers without leading or progress operands. */
trait DefaultAnalysisTrait
{
    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return [];
    }

    /** @return list<Parser> */
    public function analysisProgressParsers(): array
    {
        return [];
    }

    public function analysisError(): ?string
    {
        return null;
    }
}
