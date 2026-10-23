<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteTemplate;

/** @internal One literal recognizer with source-free recipes for the original child trees. */
final class LiteralSequence
{
    /**
     * @param list<Parser> $parsers Original operands for local failure fallback.
     * @param list<ConcreteTemplate> $children Operand-relative syntax, shared within preparation.
     * @param list<Text>|null $failureParsers Flat operands whose successful prefixes can be skipped.
     */
    private function __construct(
        private Text $terminal,
        private array $parsers,
        private array $children,
        private ?array $failureParsers,
    ) {
        // Prepared once for a frozen graph; parsing instantiates fresh source-bound nodes.
    }

    /** @return list<Parser|self> Empty when there is no useful literal run. */
    public static function prepare(Sequence $sequence, ?Preparation $preparation = null): array
    {
        $preparation ??= new Preparation();
        $steps = [];
        $run = [];
        $literal = '';
        $nodes = 0;
        foreach ($sequence as $child) {
            $compiled = $preparation->literal($child);
            if (
                $compiled === null
                || strlen($literal) + strlen($compiled[0]) > Preparation::MAX_LITERAL_BYTES
                || $nodes + $compiled[1] > Preparation::MAX_LITERAL_NODES
            ) {
                self::appendRun($steps, $run, $literal, $preparation);
                $run = [];
                $literal = '';
                $nodes = 0;
            }
            if ($compiled === null) {
                $steps[] = $child;
                continue;
            }
            $run[] = $child;
            $literal .= $compiled[0];
            $nodes += $compiled[1];
        }
        self::appendRun($steps, $run, $literal, $preparation);
        foreach ($steps as $step) {
            if ($step instanceof self) {
                return $steps;
            }
        }

        return [];
    }

    /**
     * @param list<Parser|self> $steps
     * @param list<Parser> $parsers
     */
    private static function appendRun(array &$steps, array $parsers, string $literal, Preparation $preparation): void
    {
        if ($literal === '' || (count($parsers) === 1 && $parsers[0]::class === Text::class)) {
            array_push($steps, ...$parsers);
            return;
        }

        // Only proven callback-free ASCII literals reach this classification parse.
        // Discard its source and execution records; templates retain static tree metadata only.
        $source = new ByteSource($literal);
        $session = new ParseSession($source);
        $offset = 0;
        $children = [];
        $failureParsers = [];
        foreach ($parsers as $child) {
            if ($child::class !== Text::class) {
                $failureParsers = null;
            } elseif ($failureParsers !== null) {
                $failureParsers[] = $child;
            }
            $prepared = $preparation->literalTemplate($child, $source, $offset, $session);
            if ($prepared === null) {
                array_push($steps, ...$parsers);
                return;
            }
            $children[] = $prepared[1];
            $offset += $prepared[0];
        }

        $steps[] = new self((new Text($literal))->caseSensitive(), $parsers, $children, $failureParsers);
    }

    /** @return list<Parser> */
    public function parsers(): array
    {
        return $this->parsers;
    }

    /** @return list<Success>|int|null Failure progress, or null for ordinary recognition and diagnostics. */
    public function attempt(Source $source, int $offset, ParseSession $session): array|int|null
    {
        if ($session->hasRecognitionLimits() || $session->hasActiveSpacing() || !$session->isCaseSensitive()) {
            return null;
        }
        $diagnostics = $session->diagnosticState();
        try {
            $match = $this->terminal->recognizeInSession($source, $offset, $session);
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }
        if (!$match instanceof Success) {
            $progress = 0;
            foreach ($this->failureParsers ?? [] as $index => $parser) {
                $end = $progress + strlen($parser->getText());
                if ($match->length < $end) {
                    // The compiled comparison proved the preceding plain ASCII operands succeeded.
                    // Preserve their entry/unit work, but only execute the failing operand for its
                    // original expectation and rule path. Finite budgets never enter this path.
                    $session->consumeWork($progress + $index, $offset);
                    $failure = $parser->recognizeInSession($source, $offset + $progress, $session);

                    return $progress + $failure->length;
                }
                $progress = $end;
            }

            return null;
        }
        $diagnostic = $session->diagnostic();
        $children = [];
        $childOffset = $offset;
        foreach ($this->children as $template) {
            $node = $template->instantiate($source, $childOffset);
            $childOffset = $node->span->end;
            $child = new Success($node->span->byteLength());
            $child->setSourceMetadata($node->span, $diagnostic?->byteOffset, $diagnostic);
            $child->setConcreteNode($node);
            $children[] = $child;
        }

        return $children;
    }
}
