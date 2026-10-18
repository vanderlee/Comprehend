<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match\Output;

use LogicException;
use Throwable;

/**
 * Evaluates and caches named result collections for Success. Combines selected child operations
 * with local callbacks, retaining evaluation errors and detecting recursive reads.
 */
trait ResultTrait
{
    /**
     * Register a collection operation for lazy result reads, independently of commit.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function addResultCallback(callable $callback)
    {
        $state = $this->executionState();
        $state->resultCallbacks[] = $callback;

        return $this;
    }

    public function getResult(?string $name = null, mixed $default = null): mixed
    {
        $results = $this->getResults();
        $key = $name ?? '';

        return array_key_exists($key, $results) ? $results[$key] : $default;
    }

    /**
     * Evaluate once, retaining either the completed collection or the thrown error.
     *
     * @return array<array-key, mixed>
     */
    public function getResults(): array
    {
        $state = $this->executionState();
        if ($state->resultError !== null) {
            throw $state->resultError;
        }
        if ($state->resultCache !== null) {
            return $state->resultCache;
        }
        if ($state->evaluatingResults) {
            throw new LogicException('Recursive named result evaluation');
        }
        $state->evaluatingResults = true;
        try {
            $results = [];
            $this->applyResultOperations($results);

            return $state->resultCache = $results;
        } catch (Throwable $error) {
            $state->resultError = $error;
            throw $error;
        } finally {
            $state->evaluatingResults = false;
        }
    }

    /**
     * Apply child operations before local operations to the same accumulator.
     * Child collection caches cannot replace these operations: concatenation and push
     * depend on the values already accumulated by earlier siblings.
     *
     * @param array<array-key, mixed> $results map of result-key => value
     */
    private function applyResultOperations(array &$results): void
    {
        $state = $this->executionState();
        /** @var self $success */
        foreach ($state->successes as $success) {
            $success->applyResultOperations($results);
        }
        foreach ($state->resultCallbacks as $callback) {
            $callback($results);
        }
    }
}
