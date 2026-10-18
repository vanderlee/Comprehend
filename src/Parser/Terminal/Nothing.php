<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Always succeeds without consuming input, representing an empty production. Useful for
 * explicit nullable grammar branches; unbounded repetition must not rely on it for progress.
 *
 * @author Martijn
 */
class Nothing extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
    use DiagnosticExpectationTrait;

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return true;
    }

    public function __toString(): string
    {
        return '0.';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        return $this->success($source, $byteOffset);
    }
}
