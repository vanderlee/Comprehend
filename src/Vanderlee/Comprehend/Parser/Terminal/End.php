<?php

namespace vanderlee\comprehend\parser\terminal;

use vanderlee\comprehend\core\Context;
use vanderlee\comprehend\parser\Parser;

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
