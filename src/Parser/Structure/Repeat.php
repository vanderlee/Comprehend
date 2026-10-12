<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of RepeatParser.
 *
 * @author Martijn
 */
class Repeat extends Parser
{
    use SpacingTrait;

    //use GreedyTrait;

    private $parser;
    private $min;
    private $max;

    public function __construct($parser, $min = 0, $max = null)
    {
        $this->parser = $this->getArgument($parser);
        $this->min = $min;
        $this->max = $max;

        if (
            $this->min < 0
            || ($this->max !== null
                && (
                    $this->max < 0
                    || $this->max < $this->min
                )
            )
        ) {
            throw new InvalidArgumentException('Invalid repeat range specified');
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

    public static function exact($parser, $times)
    {
        return new self($parser, $times, $times);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $childMatches = [];

        $this->pushSpacer($context);

        try {
            $length = 0;
            $matched = true;
            while (
                $matched
                && ($this->max === null || count($childMatches) < $this->max)
            ) {
                // No skipping at very start
                $spacingMatch = null;
                $skip = $length > 0
                    ? $context->skipSpacing($input, $offset + $length, $spacingMatch)
                    : 0;

                $match = $skip !== false
                    ? $this->parser->parseInContext($input, $offset + $length + $skip, $context)
                    : null;

                $matched = $match instanceof Success;
                if ($matched) {
                    if ($match->length === 0) {
                        throw new LogicException('Repeat child matched successfully without consuming input');
                    }

                    if ($spacingMatch !== null) {
                        $match->addPrecedingMatch($spacingMatch);
                    }
                    $length += $skip + $match->length;
                    $childMatches[] = $match;
                }
            }
        } finally {
            $this->popSpacer($context);
        }

        return count($childMatches) >= $this->min
            ? $this->success($input, $offset, $length, $childMatches)
            : $this->failure($input, $offset, $length);
    }

    public function __toString()
    {
        // Output ABNF formatting

        $min = $this->min > 0
            ? $this->min
            : '';
        $max = $this->max === null
            ? ''
            : $this->max;

        return ($min === $max
                ? $min
                : ($min . '*' . $max)) . $this->parser;
    }
}
