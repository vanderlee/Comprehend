<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match\Output;

use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Match\EffectExecution;
use Vanderlee\Comprehend\Match\CommitEffects;

/**
 * Executes selected Success effects once during explicit commit. Preserves preceding-match and
 * child ordering and retains thrown errors to prevent effects from being retried.
 */
trait CallbackTrait
{
    /**
     * Retain accepted effects before this match's callbacks, without adding
     * named collections. Used for trivia and processed rules.
     *
     * @internal
     */
    public function addPrecedingMatch(Success $match): void
    {
        // A completed stateless child has no deferred operations to retain.
        if ($match->state === null) {
            return;
        }
        $state = $this->executionState();
        $state->precedingMatches[] = $match;
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
        $state = $this->executionState();
        $state->customCallbacks[] = $callback;

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
        $state = $this->executionState();
        return $state->effectExecution ??= new EffectExecution();
    }

    private function runSelectedEffects(): void
    {
        $state = $this->executionState();
        // Preceding matches retain trivia and processed-rule effects outside the collection graph.
        foreach ($state->precedingMatches as $match) {
            $match->processCustomCallbacks();
        }

        /** @var self $success */
        foreach ($state->successes as $success) {
            $success->processCustomCallbacks();
        }

        foreach ($state->customCallbacks as $callback) {
            // Internal bindings borrow the owner rather than retaining a self-cycle.
            if ($callback instanceof CommitEffects) {
                $callback($this);
            } else {
                $callback();
            }
        }
    }
}
