<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Matches always, with length zero.
 * This serves as a placeholder for badly written BNF specifications and should not be used in normal situations.
 * Do NOT use this within repetitions!
 *
 * @author Martijn
 */
class Nothing extends Parser
{
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        return $this->success($input, $offset);
    }
    public function __toString(): string
    {
        return '0.';
    }
}
