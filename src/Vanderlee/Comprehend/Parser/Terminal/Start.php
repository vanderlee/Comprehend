<?php

namespace vanderlee\comprehend\parser\terminal;

use vanderlee\comprehend\core\Context;
use vanderlee\comprehend\parser\Parser;

/**
 * Matches (with length 0) if the offset is at the very start of the input
 *
 * @author Martijn
 */
class Start extends Parser
{

    protected function parse(&$input, $offset, Context $context)
    {
        return $offset == 0 ? $this->success($input, $offset) : $this->failure($input, $offset);
    }

    public function __toString()
    {
        return 'start';
    }

}
