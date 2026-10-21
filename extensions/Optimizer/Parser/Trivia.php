<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Directive\Trivia as OrdinaryTrivia;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** @internal Scan plain trivia once, retaining its original operand tree without a child match. */
class Trivia extends OrdinaryTrivia
{
    use ExactClassSnapshotReuseTrait;

    private ?ReadRun $scanner = null;
    private bool $repeated = false;
    private bool $nullable = false;

    public static function from(OrdinaryTrivia $original): self
    {
        $copy = new self($original->getParser());
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(): void
    {
        $this->scanner = null;
        $this->repeated = false;
        $this->nullable = false;
        if ($this->parser->hasCompositionMetadata()) {
            return;
        }
        if (in_array($this->parser::class, [ReadWhileSet::class, ReadWhileRange::class], true)) {
            $this->scanner = $this->parser;
        } elseif ($this->parser::class === Repeat::class && !$this->parser->hasExplicitSpacing()) {
            $this->scanner = GraphOptimizer::scanner(
                $this->parser->getParser(),
                $this->parser->getMinimum(),
                $this->parser->getMaximum(),
            );
            $this->repeated = $this->scanner !== null;
        }
        $this->nullable = $this->scanner?->analysisNullability(new NullabilityLookup([])) === true;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->scanner = null;
        $this->repeated = false;
        $this->nullable = false;
    }

    public function tryConcreteSyntax(Source $source, int $byteOffset, ParseSession $session): ?ConcreteNode
    {
        if (
            $this::class !== self::class || !$this->nullable || $this->hasCompositionMetadata()
            || $session->hasRecognitionLimits()
        ) {
            return null;
        }
        if ($session->source() !== $source) {
            throw new InvalidArgumentException('Recognition must use the session source');
        }
        if ($byteOffset < 0 || $byteOffset > $source->byteLength()) {
            throw new InvalidArgumentException('Span must satisfy 0 <= start <= end <= source byte length');
        }
        if (!$source->isUnitBoundary($byteOffset)) {
            throw new InvalidArgumentException('Parser position is inside a UTF-8 code point');
        }
        // Keep the wrapper's work charge even though it needs no execution record.
        $session->consumeWork(1, $byteOffset);
        [$length, $children] = $this->scanOperand($source, $byteOffset, $session)
            ?? throw new LogicException('Prepared nullable trivia must accept input');
        if (!$source->isUnitBoundary($byteOffset + $length)) {
            throw new LogicException('Parser returned a partial UTF-8 code point');
        }
        $span = $source->span($byteOffset, $byteOffset + $length);

        return new ConcreteNode(
            $this->concreteKind(),
            $span,
            [new ConcreteNode($this->parser->concreteKind(), $span, $children)],
            true,
        );
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        if ($this->scanner === null || $session->hasRecognitionLimits()) {
            return parent::recognize($source, $byteOffset, $session);
        }
        $scan = $this->scanOperand($source, $byteOffset, $session);
        if ($scan === null) {
            return parent::recognize($source, $byteOffset, $session);
        }
        [$length, $children] = $scan;
        $success = $this->success($source, $byteOffset, $length);
        $span = $success->span ?? throw new LogicException('Prepared trivia requires source-bound recognition');
        $success->setConcreteNodes([new ConcreteNode($this->parser->concreteKind(), $span, $children)]);

        return $success;
    }

    /** @return array{int, list<ConcreteNode>}|null Null retains ordinary minimum-failure diagnostics. */
    private function scanOperand(Source $source, int $byteOffset, ParseSession $session): ?array
    {
        $children = [];
        if ($this->repeated && $this->parser instanceof Repeat) {
            $scan = GraphOptimizer::scanRepeat(
                $this->scanner,
                $this->parser->getParser(),
                $source,
                $byteOffset,
                $session,
            );
            if ($scan === null) {
                return null;
            }
            [$length, $children] = $scan;
        } else {
            [$accepted, $length] = $this->scanner?->scanLength($source, $byteOffset, $session)
                ?? throw new LogicException('Prepared trivia requires a scanner');
            if (!$accepted) {
                // Failed minimums use the operand's ordinary diagnostic and progress path.
                return null;
            }
        }
        return [$length, $children];
    }
}
