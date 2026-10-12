<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
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
            throw new InvalidArgumentException('Less than 2 arguments provided');
        }
        $this->parsers = self::getArguments($arguments);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $length = PHP_INT_MAX;
        $matches = [];
        foreach ($this->parsers as $parser) {
            $match = $parser->parseInContext($input, $offset, $context);
            $length = min($length, $match->length);
            if (!($match instanceof Success)) {
                return $this->failure($input, $offset, $length);
            }
            $matches[] = $match;
        }

        $success = $this->success($input, $offset, $length);
        $success->setConcreteMatches($matches);
        return $success;
    }

    public function __toString()
    {
        return '( ' . implode(' + ', $this->parsers) . ' )';
    }
}
