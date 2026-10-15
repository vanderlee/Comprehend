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
     * Completed collection, including an empty collection; null means it has not been cached.
     *
     * @var array<array-key, mixed>|null
     */
    private ?array $resultCache = null;
    private ?Throwable $resultError = null;
    private bool $evaluatingResults = false;
    /**
     * Ordered operations that update a collection shared with selected children.
     *
     * @var callable[]
     */
    private array $resultCallbacks = [];

    /**
     * Register a collection operation for lazy result reads, independently of commit.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function addResultCallback(callable $callback)
    {
        $this->resultCallbacks[] = $callback;

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
        if ($this->resultError !== null) {
            throw $this->resultError;
        }
        if ($this->resultCache !== null) {
            return $this->resultCache;
        }
        if ($this->evaluatingResults) {
            throw new LogicException('Recursive named result evaluation');
        }
        $this->evaluatingResults = true;
        try {
            $results = [];
            $this->applyResultOperations($results);

            return $this->resultCache = $results;
        } catch (Throwable $error) {
            $this->resultError = $error;
            throw $error;
        } finally {
            $this->evaluatingResults = false;
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
        /** @var self $success */
        foreach ($this->successes as $success) {
            $success->applyResultOperations($results);
        }
        foreach ($this->resultCallbacks as $callback) {
            $callback($results);
        }
    }
}
