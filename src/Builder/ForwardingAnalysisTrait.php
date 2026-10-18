<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

/** Structural analysis for parsers that delegate consumption to one operand. */
trait ForwardingAnalysisTrait
{
    use DefaultAnalysisTrait;

    abstract protected function analysisTarget(): Parser;

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $lookup->get($this->analysisTarget());
    }

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return [$this->analysisTarget()];
    }
}
