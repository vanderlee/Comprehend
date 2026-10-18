<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use LogicException;
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
 * Greedily recognizes a child within repetition bounds, with optional intervening trivia.
 * Rejects non-progressing matches to prevent nullable children from causing unbounded loops.
 */
class Repeat extends Parser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
    use SpacingTrait;

    public function __construct(private Parser $parser, private int $min = 0, private ?int $max = null)
    {
        if ($min < 0 || ($max !== null && $max < $min)) {
            throw new InvalidArgumentException('Invalid repeat range specified');
        }
    }

    protected function copyConfigurationTo(Parser $copy): void
    {
        if (!$copy instanceof self) {
            throw new InvalidArgumentException('Repeat configuration requires a Repeat destination');
        }
        parent::copyConfigurationTo($copy);
        $copy->parser = $this->parser;
        $copy->min = $this->min;
        $copy->max = $this->max;
        $copy->spacer = $this->spacer;
        $copy->hasSpacingPolicy = $this->hasSpacingPolicy;
    }

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /** @internal Inclusive minimum for graph analysis. */
    public function getMinimum(): int
    {
        return $this->min;
    }

    /** @internal Null denotes an unbounded repetition. */
    public function getMaximum(): ?int
    {
        return $this->max;
    }

    public static function plus(Parser $parser): self
    {
        return new self($parser, 1, null);
    }

    public static function star(Parser $parser): self
    {
        return new self($parser, 0, null);
    }

    public static function optional(Parser $parser): self
    {
        return new self($parser, 0, 1);
    }

    public static function exact(Parser $parser, int $times): self
    {
        return new self($parser, $times, $times);
    }

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return $this->max === 0 ? [] : [$this->parser];
    }

    /** @return list<Parser> */
    public function analysisProgressParsers(): array
    {
        return $this->max === 0 ? [] : [$this->parser];
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $this->min === 0 ? true : $lookup->get($this->parser);
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parser = $context->copy($this->parser);
        $this->copySpacer($context);
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return $this->childrenWithSpacer([$this->parser]);
    }

    public function __toString(): string
    {
        // Output ABNF formatting
        $min = $this->min > 0 ? $this->min : '';
        $max = $this->max ?? '';

        return ($min === $max ? $min : $min . '*' . $max) . $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $this->pushSpacer($session);
        try {
            return $this->recognizeRepetition($source, $byteOffset, $session);
        } finally {
            $this->popSpacer($session);
        }
    }

    protected function attemptChild(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        return $this->parser->recognizeInSession($source, $offset, $session);
    }

    protected function recognizeRepetition(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $childMatches = [];
        $concreteMatches = null;
        $acceptedByteLength = 0;
        while ($this->max === null || count($childMatches) < $this->max) {
            $diagnostics = $session->diagnosticState();
            $spacingMatch = null;
            $skip = $acceptedByteLength > 0
                ? $session->skipSpacing($source, $byteOffset + $acceptedByteLength, $spacingMatch)
                : 0;
            $match = null;
            if ($skip !== false) {
                $offset = $byteOffset + $acceptedByteLength + $skip;
                $match = $this->attemptChild($source, $offset, $session);
            }
            if (!$match instanceof Success) {
                // Once the minimum is met, a failed extension ends the repeat silently.
                // Its spacing and child progress are not part of the accepted prefix.
                if (count($childMatches) >= $this->min) {
                    $session->restoreDiagnostics($diagnostics);
                }
                break;
            }

            if ($match->length === 0) {
                throw new LogicException('Repeat child matched successfully without consuming input');
            }
            // Retain trivia only after the following child has consumed input successfully.
            if ($spacingMatch !== null) {
                $match->addPrecedingMatch($spacingMatch);
                $concreteMatches ??= $childMatches;
                $concreteMatches[] = $spacingMatch;
            }
            $acceptedByteLength += $skip + $match->length;
            $childMatches[] = $match;
            if ($concreteMatches !== null) {
                $concreteMatches[] = $match;
            }
        }
        if (count($childMatches) < $this->min) {
            return $this->failure($source, $byteOffset, $acceptedByteLength);
        }
        $success = $this->success($source, $byteOffset, $acceptedByteLength, $childMatches);
        if ($concreteMatches !== null) {
            $success->setConcreteMatches($concreteMatches);
        }

        return $success;
    }
}
