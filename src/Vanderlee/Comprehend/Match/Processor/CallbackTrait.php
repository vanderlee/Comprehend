<?php

namespace vanderlee\comprehend\match\processor;

trait CallbackTrait
{
    /**
     * List of ordinary callbacks to process
     * @var callable[]
     */
    private $customCallbacks = [];

    /**
     * Add a callback to this match, to be called after parsing is finished and
     * only if this match was part of the matched rules.
     *
     * @param callable $callback
     * @return $this
     */
    public function addCustomCallback(callable $callback)
    {
        $this->customCallbacks[] = $callback;

        return $this;
    }

    /**
     * Handle all registered custom callbacks for this match and any matches
     * at deeper levels of this match.
     */
    private function processCustomCallbacks()
    {
        foreach ($this->successes as $success) {
            $success->processCustomCallbacks();
        }

        foreach ($this->customCallbacks as $callback) {
            $callback();
        }
    }

    public function getCallback() {
        $this->processCustomCallbacks();
    }
}