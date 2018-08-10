<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

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
    private $min = null;
    private $max = null;

    public function __construct($parser, $min = 0, $max = null)
    {
        $this->parser = $this->getArgument($parser);
        $this->min = $min;
        $this->max = $max;

        if ($this->max !== null && $this->max < $this->min) {
            throw new \InvalidArgumentException('Invalid repeat range specified');
        }
    }

    public static function oneOrMore($parser)
    {
        return new self($parser, 1);
    }

    public static function zeroOrMore($parser)
    {
        return new self($parser);
    }

    public static function zeroOrOne($parser)
    {
        return new self($parser, 0, 1);
    }

    public static function optional($parser)
    {
        return self::zeroOrOne($parser);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushSpacer($context);

        $child_matches = [];

        $length = 0;
        do {
            $skip = $context->skipSpacing($input, $offset + $length);
            $match = $this->parser->parse($input, $offset + $length + $skip, $context);
            if ($match->match) {
                $length += $skip + $match->length;
                $child_matches[] = $match;
            }
        } while ($match->match && ($this->max == null || count($child_matches) < $this->max));

        $match = (count($child_matches) >= $this->min) && ($this->max == null || count($child_matches) <= $this->max);

        $this->popSpacer($context);

        return $match ? $this->success($input, $offset, $length, $child_matches) : $this->failure($input, $offset, $length);
    }

    public function __toString()
    {
        // Output ABNF formatting

        $min = $this->min > 0 ? $this->min : '';
        $max = $this->max === null ? '' : $this->max;

        return ($min === $max ? $min : ($min . '*' . $max)) . $this->parser;
    }

}
