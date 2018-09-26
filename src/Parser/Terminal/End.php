<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Matches (with length 0) if the offset is at the very end of the input
 *
 * @author Martijn
 */
class End extends Parser
{

    protected function parse(&$input, $offset, Context $context)
    {
        return $offset == mb_strlen($input) ? $this->success($input, $offset) : $this->failure($input, $offset);
    }

    public function __toString()
    {
        return 'end';
    }

}
