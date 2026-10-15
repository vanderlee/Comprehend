<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Asserts the absolute end of the source without consuming input. Lets grammars express an end
 * boundary within ordinary parser composition.
 *
 * @author Martijn
 */
class End extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return true;
    }

    public function __toString(): string
    {
        return 'end';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        return $byteOffset === $source->byteLength()
            ? $this->success($source, $byteOffset)
            : $this->failure($source, $byteOffset);
    }
}
