<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Matches if and only if all parsers individually match. Returned length is the shortest length of all matches.
 *
 * @author Martijn
 */
class All extends Parser
{
    use ArgumentsTrait;

    /**
     * @var Parser[]
     */
    private $parsers = [];

    public function __construct(...$arguments)
    {
        if (count($arguments) < 2) {
            throw new \InvalidArgumentException('Less than 2 arguments provided');
        }
        $this->parsers = self::getArguments($arguments);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $length = PHP_INT_MAX;
        foreach ($this->parsers as $parser) {
            $match = $parser->parse($input, $offset, $context);
            $length = min($length, $match->length);
            if (!($match instanceof Success)) {
                return $this->failure($input, $offset, $length);
            }
        }

        return $this->success($input, $offset, $length);
    }

    public function __toString()
    {
        return '( '.implode(' + ', $this->parsers).' )';
    }
}
