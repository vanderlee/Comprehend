<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer;

use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadRun;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** @internal Batch plain scanner operands without collapsing their concrete syntax. */
final class ScannerSequence
{
    /** @param list<ReadRun> $parsers */
    private function __construct(private readonly array $parsers)
    {
        // The frozen plan owns grammar references only; matches remain local to each attempt.
    }

    /**
     * @param list<Parser|LiteralSequence> $steps
     * @return list<Parser|LiteralSequence|self>
     */
    public static function prepare(array $steps): array
    {
        $prepared = [];
        $run = [];
        foreach ($steps as $step) {
            if (
                $step instanceof ReadRun
                && in_array($step::class, [ReadWhileSet::class, ReadWhileRange::class], true)
                && !$step->hasCompositionMetadata()
            ) {
                $run[] = $step;
                continue;
            }
            self::appendRun($prepared, $run);
            $run = [];
            $prepared[] = $step;
        }
        self::appendRun($prepared, $run);

        return $prepared;
    }

    /**
     * @param list<Parser|LiteralSequence|self> $steps
     * @param list<ReadRun> $run
     */
    private static function appendRun(array &$steps, array $run): void
    {
        if (count($run) > 1) {
            $steps[] = new self($run);
        } else {
            array_push($steps, ...$run);
        }
    }

    /** @return list<ReadRun> */
    public function parsers(): array
    {
        return $this->parsers;
    }

    /** @return list<Success>|null Failed runs retain ordinary recognition and diagnostics. */
    public function attempt(Source $source, int $offset, ParseSession $session): ?array
    {
        if ($session->hasRecognitionLimits() || $session->hasActiveSpacing() || !$session->isCaseSensitive()) {
            return null;
        }
        $children = [];
        $diagnostic = $session->diagnostic();
        foreach ($this->parsers as $parser) {
            // Keep logical parser-entry work even though no checked child dispatch is needed.
            $session->consumeWork(1, $offset);
            [$accepted, $length] = $parser->scanLength($source, $offset, $session);
            if (!$accepted) {
                // These exact, metadata-free scanners cannot run user callbacks on retry.
                return null;
            }
            $span = $source->span($offset, $offset + $length);
            $child = new Success($length);
            $child->setSourceMetadata($span, $diagnostic?->byteOffset, $diagnostic);
            $child->setConcreteNode(new ConcreteNode($parser->concreteKind(), $span));
            $children[] = $child;
            $offset += $length;
        }

        return $children;
    }
}
