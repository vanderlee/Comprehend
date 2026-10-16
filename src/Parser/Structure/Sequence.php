<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Recognizes child parsers in order to build a compound match. Applies scoped intervening
 * spacing and retains selected children and trivia for the concrete tree.
 *
 * @author Martijn
 */
class Sequence extends IterableParser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
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

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        $leading = [];
        foreach ($this->parsers as $parser) {
            $leading[] = $parser;
            if ($lookup->get($parser) !== true) {
                break;
            }
        }

        return $leading;
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $lookup->every(array_values($this->parsers));
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->copySpacer($context);
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return $this->childrenWithSpacer(parent::children());
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
            $progressByteLength = 0;
            foreach ($this->parsers as $parser) {
                $spacingMatch = null;
                if ($childMatches !== []) {
                    $skip = $session->skipSpacing($source, $byteOffset + $progressByteLength, $spacingMatch);
                    if ($skip === false) {
                        return $this->failure($source, $byteOffset, $progressByteLength);
                    }
                    $progressByteLength += $skip;
                }
                $match = $parser->recognizeInSession($source, $byteOffset + $progressByteLength, $session);
                // Sequence reports observed progress, including a failed child's partial match.
                $progressByteLength += $match->length;
                if (!$match instanceof Success) {
                    return $this->failure($source, $byteOffset, $progressByteLength);
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
        $success = $this->success($source, $byteOffset, $progressByteLength, $childMatches);
        $success->setConcreteMatches($concreteMatches);
        $this->prepareSequenceValue($success, $childMatches);

        return $success;
    }

    /**
     * Specializations may select a lazy value without changing syntax or lifecycle children.
     * @param list<Success> $children Operand matches, excluding automatic trivia.
     */
    protected function prepareSequenceValue(Success $success, array $children): void
    {
        // Ordinary sequences keep their own raw text as the default value.
    }
}
