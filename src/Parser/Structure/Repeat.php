<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of RepeatParser
 *
 * @author Martijn
 */
class Repeat extends Parser
{

    use SpacingTrait;

    //use GreedyTrait;

    private $parser = null;
    private $min    = null;
    private $max    = null;

    public function __construct($parser, $min = 0, $max = null)
    {
        $this->parser = $this->getArgument($parser);
        $this->min    = $min;
        $this->max    = $max;

        if ($this->max !== null && $this->max < $this->min) {
            throw new \InvalidArgumentException('Invalid repeat range specified');
        }
    }

    public static function plus($parser)
    {
        return new self($parser, 1, null);
    }

    public static function star($parser)
    {
        return new self($parser, 0, null);
    }

    public static function optional($parser)
    {
        return new self($parser, 0, 1);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushSpacer($context);

        $child_matches = [];

        $match  = null;
        $length = 0;
        do {
            // No skipping at very start
            $skip = $length > 0 ? $context->skipSpacing($input, $offset + $length) : 0;
            if ($skip !== false) {
                $match = $this->parser->parse($input, $offset + $length + $skip, $context);
                if ($match instanceof Success) {
                    $length          += $skip + $match->length;
                    $child_matches[] = $match;
                }
            }
        } while ($skip !== false && $match->match && ($this->max == null || count($child_matches) < $this->max));

        $match = (count($child_matches) >= $this->min) && ($this->max == null || count($child_matches) <= $this->max);

        $this->popSpacer($context);

        return $match ? $this->success($input, $offset, $length, $child_matches)
            : $this->failure($input, $offset, $length);
    }

    public function __toString()
    {
        // Output ABNF formatting

        $min = $this->min > 0 ? $this->min : '';
        $max = $this->max === null ? '' : $this->max;

        return ($min === $max ? $min : ($min . '*' . $max)) . $this->parser;
    }

}
