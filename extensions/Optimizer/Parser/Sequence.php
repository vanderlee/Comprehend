<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\LiteralSequence;
use Vanderlee\Comprehend\Extensions\Optimizer\Preparation;
use Vanderlee\Comprehend\Extensions\Optimizer\ScannerSequence;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence as OrdinarySequence;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** @internal Batched literal and scanner recognition retaining every original child node. */
class Sequence extends OrdinarySequence
{
    use ExactClassSnapshotReuseTrait;
    use SyntaxSpacingTrait;

    /** @var list<Parser|LiteralSequence|ScannerSequence> */
    private array $steps = [];

    public static function from(OrdinarySequence $original): self
    {
        $copy = new self(...iterator_to_array($original));
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(?Preparation $preparation = null): void
    {
        $literalSteps = LiteralSequence::prepare($this, $preparation);
        $steps = ScannerSequence::prepare($literalSteps !== [] ? $literalSteps : iterator_to_array($this, false));
        $this->steps = [];
        foreach ($steps as $step) {
            if ($step instanceof LiteralSequence || $step instanceof ScannerSequence) {
                $this->steps = $steps;
                break;
            }
        }
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->steps = [];
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        if (
            $this->steps === [] || $this->hasExplicitSpacing()
            || $session->hasRecognitionLimits() || $session->hasActiveSpacing() || !$session->isCaseSensitive()
        ) {
            return parent::recognize($source, $byteOffset, $session);
        }

        // Without automatic spacing, adjacent plain operands can be recognized in batches.
        // Failed runs recover diagnostics locally: earlier executable operands must never run twice.
        $children = [];
        $concreteChildren = null;
        $length = 0;
        foreach ($this->steps as $step) {
            if ($step instanceof LiteralSequence || $step instanceof ScannerSequence) {
                $batch = $step->attempt($source, $byteOffset + $length, $session);
                if (is_int($batch)) {
                    return $this->failure($source, $byteOffset, $length + $batch);
                }
                if ($batch !== null) {
                    foreach ($batch as $child) {
                        $length += $child->span->byteLength();
                        $concreteChildren ??= $children;
                        $concreteChildren[] = $child;
                    }
                    continue;
                }
            }
            $operands = $step instanceof LiteralSequence || $step instanceof ScannerSequence
                ? $step->parsers() : [$step];
            foreach ($operands as $parser) {
                $child = $parser->recognizeInSession($source, $byteOffset + $length, $session);
                $length += $child->length;
                if (!$child instanceof Success) {
                    return $this->failure($source, $byteOffset, $length);
                }
                $children[] = $child;
                if ($concreteChildren !== null) {
                    $concreteChildren[] = $child;
                }
            }
        }

        // A full-width literal operand can share its immutable interval with the parent,
        // just as Parser::success() does when a child execution record is present.
        $firstConcrete = $concreteChildren[0] ?? null;
        $success = $children === [] && $firstConcrete instanceof ConcreteNode
            && $firstConcrete->span->start === $byteOffset && $firstConcrete->span->end === $byteOffset + $length
            ? $this->successFromSpan($firstConcrete->span)
            : $this->success($source, $byteOffset, $length, $children);
        if ($concreteChildren !== null) {
            $success->setConcreteChildren($concreteChildren);
        }

        return $success;
    }
}
