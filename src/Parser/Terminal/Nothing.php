<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Matches always, with length zero.
 * This serves as a placeholder for badly written BNF specifications and should not be used in normal situations.
 * Do NOT use this within repetitions!
 *
 * @author Martijn
 */
class Nothing extends Parser
{
    public function __toString(): string
    {
        return '0.';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        return $this->success($source, $byteOffset);
    }
}
