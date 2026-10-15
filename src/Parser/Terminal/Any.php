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
 * Consumes one available source unit without constraining its value. ParseSession supplies an
 * octet in byte mode or a code point in UTF-8 mode.
 *
 * @author Martijn
 */
class Any extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return false;
    }

    public function __toString(): string
    {
        return '.';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $unit = $session->readSourceUnit($byteOffset);

        return $unit !== null
            ? $this->success($source, $byteOffset, $unit->byteLength())
            : $this->failure($source, $byteOffset);
    }
}
