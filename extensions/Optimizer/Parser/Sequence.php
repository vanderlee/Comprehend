<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\LiteralSequence;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\Sequence as OrdinarySequence;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal Batched literal recognition retaining every original child node. */
class Sequence extends OrdinarySequence
{
    use ExactClassSnapshotReuseTrait;

    private ?LiteralSequence $literal = null;

    public static function from(OrdinarySequence $original): self
    {
        $copy = new self(...iterator_to_array($original));
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(): void
    {
        $this->literal = LiteralSequence::prepare($this);
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->literal = null;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $children = $this->literal?->attempt($source, $byteOffset, $session);
        if ($children === null) {
            return parent::recognize($source, $byteOffset, $session);
        }
        $length = 0;
        foreach ($children as $child) {
            $length += $child->length;
        }

        return $this->success($source, $byteOffset, $length, $children);
    }
}
