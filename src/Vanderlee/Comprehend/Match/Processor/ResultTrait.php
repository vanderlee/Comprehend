<?php

namespace vanderlee\comprehend\match\processor;

trait ResultTrait
{

    /**
     * Map of resolved result callbacks
     *
     * @var array|null
     */
    private $results = null;

    /**
     * List of partial-resolvable result callbacks
     * @var callable[]
     */
    private $resultCallbacks = [];

    /**
     * Add a callback to this match, to be called after parsing is finished and
     * only if this match was part of the matched rules.
     *
     * @param callable $callback
     * @return $this
     */
    public function addResultCallback(callable $callback)
    {
        $this->resultCallbacks[] = $callback;

        return $this;
    }
    /**
     * Handle all registered result callbacks for this match and any matches
     * at deeper levels of this match.
     *
     * @param array $results map of result-key => value
     */
    private function processResultCallbacks(&$results)
    {
        foreach ($this->successes as $success) {
            $success->processResultCallbacks($results);
        }

        foreach ($this->resultCallbacks as $callback) {
            $callback($results);
        }
    }

    /**
     * Pre-calculate results
     *
     * @return array
     */
    public function getResults()
    {
        if ($this->results === null) {
            $this->results = [];
            $this->processResultCallbacks($this->results);
        }

        return $this->results;
    }

    //@todo deprecate? Saves no time and is part of collection
    public function getResult($name = null, $default = null)
    {
        return $this->getResults()[$name] ?? $default;
    }

    //@todo deprecate? Saves no time and is part of collection
    public function hasResult($name = null)
    {
        return isset($this->getResults()[$name]);
    }
}