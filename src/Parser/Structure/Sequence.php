<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of SequenceParser.
 *
 * @author Martijn
 */
class Sequence extends IterableParser
{
    use SpacingTrait;

    public function __construct(...$arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('No arguments');
        }

        $this->parsers = self::getArguments($arguments, false);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $childMatches = [];
        $concreteMatches = [];

        $this->pushSpacer($context);

        try {
            $total = 0;
            /** @var Parser $parser */
            foreach ($this->parsers as $parser) {
                $spacingMatch = null;
                if ($total > 0) {
                    $skip = $context->skipSpacing($input, $offset + $total, $spacingMatch);
                    if ($skip === false) {
                        return $this->failure($input, $offset, $total);
                    }
                    $total += $skip;
                }
                $match = $parser->parseInContext($input, $offset + $total, $context);
                $total += $match->length;

                if (!($match instanceof Success)) {  // must match
                    return $this->failure($input, $offset, $total);
                }

                if ($spacingMatch !== null) {
                    $match->addPrecedingMatch($spacingMatch);
                    $concreteMatches[] = $spacingMatch;
                }
                $childMatches[] = $match;
                $concreteMatches[] = $match;
            }
        } finally {
            $this->popSpacer($context);
        }

        $success = $this->success($input, $offset, $total, $childMatches);
        $success->setConcreteMatches($concreteMatches);
        return $success;
    }

    /**
     * Add one or more parsers to the end of this sequence.
     *
     * @param string[]|int[]|Parser[] $arguments
     *
     * @return $this
     */
    public function add(...$arguments)
    {
        $this->parsers = array_merge($this->parsers, self::getArguments($arguments));

        return $this;
    }

    public function __toString()
    {
        return '( ' . implode(' ', $this->parsers) . ' )';
    }
}
