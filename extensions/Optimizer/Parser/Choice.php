<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\ChoiceDispatch;
use Vanderlee\Comprehend\Extensions\Optimizer\ChoicePrefix;
use Vanderlee\Comprehend\Extensions\Optimizer\GraphOptimizer;
use Vanderlee\Comprehend\Extensions\Optimizer\LexicalTable;
use Vanderlee\Comprehend\Extensions\Optimizer\Preparation;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice as OrdinaryChoice;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

/** @internal Ordinary selection control flow with extension-owned operand recognition. */
class Choice extends OrdinaryChoice
{
    use ExactClassSnapshotReuseTrait;

    /** @var array<array-key, LexicalTable> */
    private array $lexical = [];

    /** @var array<array-key, ChoiceDispatch> */
    private array $dispatch = [];
    private ?ChoicePrefix $prefix = null;
    /** @var array<int, int|null>|null Recognition-local edge comparisons. */
    private ?array $prefixProgress = null;

    public static function from(OrdinaryChoice $original): self
    {
        $copy = new self(...iterator_to_array($original));
        $original->copyConfigurationTo($copy);

        return $copy;
    }

    public function prepareRecognition(?Preparation $preparation = null): void
    {
        $this->lexical = GraphOptimizer::choiceTables($this, $preparation);
        $this->dispatch = ChoiceDispatch::alternatives($this, $this->lexical);
        $this->prefix = ChoicePrefix::prepare($this);
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->lexical = [];
        $this->dispatch = [];
        $this->prefix = null;
        $this->prefixProgress = null;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        if (
            $this->prefix === null || static::class !== self::class || $session->hasRecognitionLimits()
            || $session->hasActiveSpacing() || !$session->isCaseSensitive()
            || !in_array($source::class, [ByteSource::class, Utf8Source::class,
                ByteFileSource::class, Utf8FileSource::class], true)
        ) {
            return parent::recognize($source, $byteOffset, $session);
        }
        $previous = $this->prefixProgress;
        $this->prefixProgress = [];
        try {
            return parent::recognize($source, $byteOffset, $session);
        } finally {
            $this->prefixProgress = $previous;
        }
    }

    protected function attemptAlternative(
        int|string $index,
        Parser $parser,
        Source $source,
        int $offset,
        ParseSession $session,
    ): AbstractMatch {
        if ($this->prefix !== null && $this->prefixProgress !== null) {
            $failure = $this->prefix->reject($index, $source, $offset, $session, $this->prefixProgress);
            if ($failure !== null) {
                return $failure;
            }
        }
        if (isset($this->dispatch[$index]) && static::class === self::class) {
            $failure = $this->dispatch[$index]->reject($source, $offset, $session);
            if ($failure !== null) {
                return $failure;
            }
        }

        return GraphOptimizer::attempt(
            $this->lexical[$index] ?? null,
            $parser,
            $source,
            $offset,
            $session,
            retainFailureDiagnostic: static::class !== self::class,
        );
    }
}
