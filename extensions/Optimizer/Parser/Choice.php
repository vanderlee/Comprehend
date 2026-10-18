<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\LexicalTable;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice as OrdinaryChoice;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** @internal Ordinary selection control flow with extension-owned operand recognition. */
class Choice extends OrdinaryChoice
{
    use ExactClassSnapshotReuseTrait;

    /** @var array<array-key, LexicalTable> */
    private array $lexical = [];

    public static function from(OrdinaryChoice $original): self
    {
        $copy = new self(...iterator_to_array($original));
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(): void
    {
        $this->lexical = GraphOptimizer::choiceTables($this);
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->lexical = [];
    }

    protected function attemptAlternative(
        int|string $index,
        Parser $parser,
        Source $source,
        int $offset,
        ParseSession $session,
    ): AbstractMatch {
        return GraphOptimizer::attempt($this->lexical[$index] ?? null, $parser, $source, $offset, $session);
    }
}
