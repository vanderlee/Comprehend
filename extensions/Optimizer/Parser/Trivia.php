<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use LogicException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
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
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->scanner = null;
        $this->repeated = false;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        if ($this->scanner === null || $session->hasRecognitionLimits()) {
            return parent::recognize($source, $byteOffset, $session);
        }
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
                return parent::recognize($source, $byteOffset, $session);
            }
            [$length, $children] = $scan;
        } else {
            [$accepted, $length] = $this->scanner->scanLength($source, $byteOffset, $session);
            if (!$accepted) {
                // Failed minimums use the operand's ordinary diagnostic and progress path.
                return parent::recognize($source, $byteOffset, $session);
            }
        }
        $success = $this->success($source, $byteOffset, $length);
        $span = $success->span ?? throw new LogicException('Prepared trivia requires source-bound recognition');
        $success->setConcreteNodes([new ConcreteNode($this->parser->concreteKind(), $span, $children)]);

        return $success;
    }
}
