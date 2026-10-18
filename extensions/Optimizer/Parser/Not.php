<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\LexicalTable;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Structure\Not as OrdinaryNot;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal Ordinary diagnostic rollback and lookahead semantics remain in the parent. */
class Not extends OrdinaryNot
{
    use ExactClassSnapshotReuseTrait;

    private ?LexicalTable $lexical = null;

    public static function from(OrdinaryNot $original): self
    {
        $copy = new self($original->getParser());
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(): void
    {
        $this->lexical = GraphOptimizer::table($this->getParser());
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->lexical = null;
    }

    protected function attemptChild(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        return GraphOptimizer::attempt($this->lexical, $this->getParser(), $source, $offset, $session);
    }
}
