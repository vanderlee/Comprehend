<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Core\Context;
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
    protected function parse(&$input, $offset, Context $context)
    {
        return $this->makeMatch($offset <= mb_strlen($input), $input, 0);
    }

    public function __toString()
    {
        return '0.';
    }
}
