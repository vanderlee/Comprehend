<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Matches any single symbol.
 *
 * @author Martijn
 */
class Any extends Parser
{
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
