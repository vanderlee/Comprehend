<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Match the first parser but not the second.
 * Essentially the same as (A - B) = (A + !B).
 *
 * @author Martijn
 */
class Except extends Parser
{
    use ArgumentsTrait;

    private $parserMatch = null;
    private $parserNot = null;

    /**
     * @param Parser|string $match
     * @param Parser|string $not
     */
    public function __construct($match, $not)
    {
        $this->parserMatch = self::getArgument($match);
        $this->parserNot = self::getArgument($not);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $match = $this->parserMatch->parse($input, $offset, $context);
        $not = $this->parserNot->parse($input, $offset, $context);

        if (($match instanceof Success) && ($not instanceof Failure)) {
            return $this->success($input, $offset, $match->length, $match);
        }

        return $this->failure($input, $offset, min($match->length, $not->length));
    }

    public function __toString()
    {
        return '( '.$this->parserMatch.' - '.$this->parserNot.' )';
    }
}
