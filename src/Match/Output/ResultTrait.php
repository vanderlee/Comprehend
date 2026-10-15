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
     * Map of resolved result callbacks.
     *
     * @var array<array-key, mixed>|null
     */
    private ?array $resultCache = null;
    private ?Throwable $resultError = null;
    private bool $evaluatingResults = false;
    /**
     * List of partial-resolvable result callbacks.
     *
     * @var callable[]
     */
    private array $resultCallbacks = [];

    /**
     * Add a callback to this match, to be called after parsing is finished and
     * only if this match was part of the matched rules.
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
     * Pre-calculate results.
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
            $this->processResultCallbacks($results);

            return $this->resultCache = $results;
        } catch (Throwable $error) {
            $this->resultError = $error;
            throw $error;
        } finally {
            $this->evaluatingResults = false;
        }
    }

    /**
     * Handle all registered result callbacks for this match and any matches
     * at deeper levels of this match.
     *
     * @param array<array-key, mixed> $results map of result-key => value
     */
    private function processResultCallbacks(array &$results): void
    {
        /** @var self $success */
        foreach ($this->successes as $success) {
            $success->processResultCallbacks($results);
        }
        foreach ($this->resultCallbacks as $callback) {
            $callback($results);
        }
    }
}
