<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match\Output;

use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Match\EffectExecution;

/**
 * Executes selected Success effects once during explicit commit. Preserves preceding-match and
 * child ordering and retains thrown errors to prevent effects from being retried.
 */
trait CallbackTrait
{
    /**
     * List of ordinary callbacks to process.
     *
     * @var callable[]
     */
    private array $customCallbacks = [];
    /** @var Success[] */
    private array $precedingMatches = [];
    private ?EffectExecution $effectExecution = null;

    /**
     * Retain accepted effects before this match's callbacks, without adding
     * named collections. Used for trivia and processed rules.
     *
     * @internal
     */
    public function addPrecedingMatch(Success $match): void
    {
        $this->precedingMatches[] = $match;
    }

    /**
     * Register a local effect for explicit commit of the selected match.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function addCustomCallback(callable $callback)
    {
        $this->customCallbacks[] = $callback;

        return $this;
    }

    public function getCallback(): void
    {
        $this->processCustomCallbacks();
    }

    /**
     * Retain completion or failure for this match's entire selected effect sequence.
     */
    private function processCustomCallbacks(): void
    {
        $this->effectExecution()->run($this->runSelectedEffects(...));
    }

    private function effectExecution(): EffectExecution
    {
        return $this->effectExecution ??= new EffectExecution();
    }

    private function runSelectedEffects(): void
    {
        // Preceding matches retain trivia and processed-rule effects outside the collection graph.
        foreach ($this->precedingMatches as $match) {
            $match->processCustomCallbacks();
        }

        /** @var self $success */
        foreach ($this->successes as $success) {
            $success->processCustomCallbacks();
        }

        foreach ($this->customCallbacks as $callback) {
            $callback();
        }
    }
}
