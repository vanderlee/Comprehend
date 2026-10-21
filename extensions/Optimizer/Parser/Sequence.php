<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\LiteralSequence;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence as OrdinarySequence;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal Batched literal recognition retaining every original child node. */
class Sequence extends OrdinarySequence
{
    use ExactClassSnapshotReuseTrait;
    use SyntaxSpacingTrait;

    /** @var list<Parser|LiteralSequence> */
    private array $steps = [];

    public static function from(OrdinarySequence $original): self
    {
        $copy = new self(...iterator_to_array($original));
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(): void
    {
        $this->steps = LiteralSequence::prepare($this);
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
        // Failed runs fall back locally: earlier executable operands must never run twice.
        $children = [];
        $length = 0;
        foreach ($this->steps as $step) {
            if ($step instanceof LiteralSequence) {
                $batch = $step->attempt($source, $byteOffset + $length, $session);
                if ($batch !== null) {
                    foreach ($batch as $child) {
                        $length += $child->length;
                        $children[] = $child;
                    }
                    continue;
                }
            }
            foreach ($step instanceof LiteralSequence ? $step->parsers() : [$step] as $parser) {
                $child = $parser->recognizeInSession($source, $byteOffset + $length, $session);
                $length += $child->length;
                if (!$child instanceof Success) {
                    return $this->failure($source, $byteOffset, $length);
                }
                $children[] = $child;
            }
        }

        return $this->success($source, $byteOffset, $length, $children);
    }
}
