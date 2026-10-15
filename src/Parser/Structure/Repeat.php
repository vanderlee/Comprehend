<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
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
class Repeat extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;
    use SpacingTrait;

    public function __construct(private Parser $parser, private int $min = 0, private ?int $max = null)
    {
        if ($min < 0 || ($max !== null && $max < $min)) {
            throw new InvalidArgumentException('Invalid repeat range specified');
        }
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

    public function __toString(): string
    {
        // Output ABNF formatting
        $min = $this->min > 0 ? $this->min : '';
        $max = $this->max ?? '';

        return ($min === $max ? $min : $min . '*' . $max) . $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $childMatches = [];
        $concreteMatches = [];
        $this->pushSpacer($session);
        try {
            $length = 0;
            while ($this->max === null || count($childMatches) < $this->max) {
                $diagnostics = $session->diagnosticState();
                $spacingMatch = null;
                $skip = $length > 0 ? $session->skipSpacing($source, $byteOffset + $length, $spacingMatch) : 0;
                $match = $skip !== false ? $this->parser->recognizeInSession(
                    $source,
                    $byteOffset + $length + $skip,
                    $session,
                ) : null;
                if (!$match instanceof Success) {
                    if (count($childMatches) >= $this->min) {
                        $session->restoreDiagnostics($diagnostics);
                    }
                    break;
                }

                if ($match->length === 0) {
                    throw new LogicException('Repeat child matched successfully without consuming input');
                }
                if ($spacingMatch !== null) {
                    $match->addPrecedingMatch($spacingMatch);
                    $concreteMatches[] = $spacingMatch;
                }
                $length += $skip + $match->length;
                $childMatches[] = $match;
                $concreteMatches[] = $match;
            }
        } finally {
            $this->popSpacer($session);
        }
        if (count($childMatches) < $this->min) {
            return $this->failure($source, $byteOffset, $length);
        }
        $success = $this->success($source, $byteOffset, $length, $childMatches);
        $success->setConcreteMatches($concreteMatches);

        return $success;
    }
}
