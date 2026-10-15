<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Match\Output;

use Vanderlee\Comprehend\Match\Success;

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
    private bool $effectsProcessed = false;
    private bool $effectsProcessing = false;
    private ?\Throwable $effectError = null;

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

    public function getCallback(): void
    {
        $this->processCustomCallbacks();
    }

    /**
     * Handle all registered custom callbacks for this match and any matches
     * at deeper levels of this match.
     */
    private function processCustomCallbacks(): void
    {
        if ($this->effectError !== null) {
            throw $this->effectError;
        }
        if ($this->effectsProcessed) {
            return;
        }
        $this->effectsProcessed = true;
        $this->effectsProcessing = true;
        try {
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
        } catch (\Throwable $error) {
            $this->effectError = $error;
            throw $error;
        } finally {
            $this->effectsProcessing = false;
        }
    }
}
