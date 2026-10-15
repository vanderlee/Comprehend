<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

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

    /** @internal Subclasses and explicit spacing policies retain their boundary. */
    public function canFlattenPrefix(): bool
    {
        return static::class === self::class && !$this->hasCompositionMetadata() && !$this->hasSpacingPolicy;
    }

    /**
     * Add one or more parsers to the end of this sequence.
     *
     * @param Parser ...$arguments
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

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $childMatches = [];
        $concreteMatches = [];
        $this->pushSpacer($session);
        try {
            $total = 0;
            foreach ($this->parsers as $parser) {
                $spacingMatch = null;
                if ($childMatches !== []) {
                    $skip = $session->skipSpacing($source, $byteOffset + $total, $spacingMatch);
                    if ($skip === false) {
                        return $this->failure($source, $byteOffset, $total);
                    }
                    $total += $skip;
                }
                $match = $parser->recognizeInSession($source, $byteOffset + $total, $session);
                $total += $match->length;
                if (!$match instanceof Success) {
                    return $this->failure($source, $byteOffset, $total);
                }
                if ($spacingMatch !== null) {
                    $match->addPrecedingMatch($spacingMatch);
                    $concreteMatches[] = $spacingMatch;
                }
                $childMatches[] = $match;
                $concreteMatches[] = $match;
            }
        } finally {
            $this->popSpacer($session);
        }
        $success = $this->success($source, $byteOffset, $total, $childMatches);
        $success->setConcreteMatches($concreteMatches);
        return $success;
    }
}
