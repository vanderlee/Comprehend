<?php

namespace vanderlee\comprehend\parser\structure;

use vanderlee\comprehend\core\ArgumentsTrait;
use vanderlee\comprehend\core\Context;
use vanderlee\comprehend\parser\Parser;

/**
 * Description of NotParser
 *
 * @author Martijn
 */
class Not extends Parser
{

    use ArgumentsTrait;

    private $parser = null;

    public function __construct($parser)
    {
        $this->parser = self::getArgument($parser);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $match = $this->parser->parse($input, $offset, $context);
        return $match->match ? $this->failure($input, $offset, $match->length) : $this->success($input, $offset, 0);
    }

    public function __toString()
    {
        return '!' . $this->parser;
    }

}
