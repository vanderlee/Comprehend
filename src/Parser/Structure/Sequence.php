<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use InvalidArgumentException;
use Vanderlee\Comprehend\Runtime\ParseSession;
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
    public function __construct(Parser ...$arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('No arguments');
        }
        $this->parsers = $arguments;
    }
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $childMatches = [];
        $concreteMatches = [];
        $this->pushSpacer($context);
        try {
            $total = 0;
            $first = true;
            /** @var Parser $parser */
            foreach ($this->parsers as $parser) {
                $spacingMatch = null;
                if (!$first) {
                    $skip = $context->skipSpacing($input, $offset + $total, $spacingMatch);
                    if ($skip === false) {
                        return $this->failure($input, $offset, $total);
                    }
                    $total += $skip;
                }
                $first = false;
                $match = $parser->recognizeInSession($input, $offset + $total, $context);
                $total += $match->length;
                if (!$match instanceof Success) {
                    // must match
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
     * @param Parser[] $arguments
     *
     * @return $this
     */
    public function add(Parser ...$arguments)
    {
        $this->parsers = array_merge($this->parsers, $arguments);
        return $this;
    }
    public function __toString(): string
    {
        return '( ' . implode(' ', $this->parsers) . ' )';
    }
}
