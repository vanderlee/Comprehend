<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\LexicalTable;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\Repeat as OrdinaryRepeat;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadRun;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal Spacing scopes and the ordinary repetition loop remain in the parent. */
class Repeat extends OrdinaryRepeat
{
    use ExactClassSnapshotReuseTrait;

    private ?ReadRun $scanner = null;
    private ?LexicalTable $lexical = null;

    public static function from(OrdinaryRepeat $original): self
    {
        $copy = new self($original->getParser(), $original->getMinimum(), $original->getMaximum());
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(): void
    {
        $this->lexical = $this->scanner === null && $this->getMaximum() !== 0
            ? GraphOptimizer::table($this->getParser()) : null;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->lexical = null;
        $this->scanner = $context->optimizationEnabled()
            ? GraphOptimizer::scanner($this->getParser(), $this->getMinimum(), $this->getMaximum()) : null;
    }

    protected function attemptChild(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        return GraphOptimizer::attempt($this->lexical, $this->getParser(), $source, $offset, $session);
    }

    protected function recognizeRepetition(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $scan = GraphOptimizer::scanRepeat($this->scanner, $this->getParser(), $source, $byteOffset, $session);

        return $scan === null
            ? parent::recognizeRepetition($source, $byteOffset, $session)
            : $this->success($source, $byteOffset, $scan[0], $scan[1]);
    }
}
