<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

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
        return ($match instanceof Success)
            ? $this->failure($input, $offset, $match->length)
            : $this->success($input, $offset, 0);
    }

    public function __toString()
    {
        return '!' . $this->parser;
    }

}
