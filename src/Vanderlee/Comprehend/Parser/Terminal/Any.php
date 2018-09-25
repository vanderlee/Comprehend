<?php

namespace vanderlee\comprehend\parser\terminal;

use vanderlee\comprehend\core\Context;
use vanderlee\comprehend\parser\Parser;

/**
 * Matches any single symbol
 *
 * @author Martijn
 */
class Any extends Parser
{

    protected function parse(&$input, $offset, Context $context)
    {
        if ($offset < mb_strlen($input)) {
            return $this->success($input, $offset, 1);
        }

        return $this->failure($input, $offset);
    }

    public function __toString()
    {
        return '.';
    }

}
