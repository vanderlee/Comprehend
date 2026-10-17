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
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\ReadRun;
use Vanderlee\Comprehend\Parser\Terminal\ReadWhileRange;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Runtime\LexicalTable;
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

    private ?ReadRun $scanner = null;
    private ?LexicalTable $lexical = null;

    /** @internal Called only on a detached, resolved and validated frozen graph. */
    public function prepareLexicalRecognition(): void
    {
        $this->lexical = $this->scanner === null && $this->max !== 0 ? LexicalTable::prepare($this->parser) : null;
    }

    /** @internal Keep the repeat boundary and its metadata while replacing its recognition plan. */
    public function withOptimizedRecognition(): self
    {
        $copy = clone $this;
        $copy->scanner = null;
        if ($this->max !== 0) {
            $copy->scanner = match ($this->parser::class) {
                Set::class, Range::class, Char::class, Text::class
                    => $this->parser->repetitionScanner($this->min, $this->max),
                // Any ignores case policy, including Unicode folds that expand to multiple units.
                Any::class => $this->parser->hasCompositionMetadata() ? null
                    : (new ReadWhileRange("\0", null, $this->min, $this->max))->caseSensitive(),
                default => null,
            };
        }

        return $copy;
    }

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

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parser = $context->copy($this->parser);
        $this->copySpacer($context);
        $this->lexical = null;
        if ($this->scanner !== null) {
            $this->scanner = clone $this->scanner;
        }
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
        $childMatches = [];
        $concreteMatches = null;
        $this->pushSpacer($session);
        try {
            if ($this->scanner !== null && !$session->hasRecognitionLimits() && !$session->hasActiveSpacing()) {
                $diagnostics = $session->diagnosticState();
                $scan = $this->scanner->recognizeInSession($source, $byteOffset, $session);
                $session->restoreDiagnostics($diagnostics);
                if ($scan instanceof Success) {
                    return $this->scannedSuccess($source, $byteOffset, $scan->length, $session);
                }
                // The original child owns minimum-failure expectations and rule paths.
            }
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
                    $useTable = $this->lexical !== null
                        && !$session->hasRecognitionLimits() && !$session->hasActiveSpacing();
                    $match = $useTable
                        ? $this->lexical->attempt($source, $offset, $session)
                        : $this->parser->recognizeInSession($source, $offset, $session);
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
        } finally {
            $this->popSpacer($session);
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

    private function scannedSuccess(Source $source, int $offset, int $length, ParseSession $session): Success
    {
        $children = [];
        $diagnostic = $session->diagnostic();
        $end = $offset + $length;
        while ($offset < $end) {
            $unit = $source->readUnit($offset);
            if ($unit === null) {
                throw new LogicException('Scanned source unit is missing');
            }
            $child = new Success($unit->byteLength());
            $child->setSourceMetadata($unit, $diagnostic?->byteOffset, $diagnostic);
            $child->buildConcreteNode($this->parser);
            $children[] = $child;
            $offset = $unit->end;
        }

        return $this->success($source, $end - $length, $length, $children);
    }
}
