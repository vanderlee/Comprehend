<?php

namespace vanderlee\comprehend\parser\structure;

use vanderlee\comprehend\core\ArgumentsTrait;
use vanderlee\comprehend\core\Context;
use vanderlee\comprehend\parser\Parser;

/**
 * Match the first parser but not the second.
 * Essentially the same as (A - B) = (A + !B)
 *
 * @author Martijn
 */
class Except extends Parser
{

    use ArgumentsTrait;

    private $parser_match = null;
    private $parser_not   = null;

    /**
     *
     * @param Parser|string $match
     * @param Parser|string $not
     */
    public function __construct($match, $not)
    {
        $this->parser_match = self::getArgument($match);
        $this->parser_not   = self::getArgument($not);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $match = $this->parser_match->parse($input, $offset, $context);
        $not   = $this->parser_not->parse($input, $offset, $context);

        if ($match->match && !$not->match) {
            return $this->success($input, $offset, $match->length, $match);
        }

        return $this->failure($input, $offset, min($match->length, $not->length));
    }

    public function __toString()
    {
        return '( ' . $this->parser_match . ' - ' . $this->parser_not . ' )';
    }

}
