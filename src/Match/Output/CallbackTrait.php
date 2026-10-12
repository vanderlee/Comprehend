<?php

namespace Vanderlee\Comprehend\Match\Output;

use Vanderlee\Comprehend\Match\Success;

trait CallbackTrait
{
    /**
     * List of ordinary callbacks to process.
     *
     * @var callable[]
     */
    private $customCallbacks = [];

    /** @var Success[] */
    private $precedingMatches = [];

    /**
     * Retain accepted effects before this match's callbacks, without adding
     * token children or named results. Used for spacing and processed rules.
     *
     * @internal
     */
    public function addPrecedingMatch(Success $match)
    {
        $this->precedingMatches[] = $match;
    }

    /**
     * Add a callback to this match, to be called after parsing is finished and
     * only if this match was part of the matched rules.
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

    /**
     * Handle all registered custom callbacks for this match and any matches
     * at deeper levels of this match.
     */
    private function processCustomCallbacks()
    {
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

    public function getCallback()
    {
        $this->processCustomCallbacks();
    }
}
