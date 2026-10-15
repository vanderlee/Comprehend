<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

/** Optional, pure structural analysis; recognition remains the Parser contract. */
interface AnalyzableParser
{
    /**
     * Conservative empty-match estimate: false, unknown (null), or true.
     * Must be deterministic and monotone in that order as child estimates increase.
     */
    public function analysisNullability(NullabilityLookup $lookup): ?bool;

    /** @return list<Parser> Operands callable before consuming input. */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array;

    /** @return list<Parser> Operands whose successful matches must consume input. */
    public function analysisProgressParsers(): array;

    /** A structural error, without its graph path, or null when locally valid. */
    public function analysisError(): ?string;
}
