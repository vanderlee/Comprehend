<?php

namespace Vanderlee\Comprehend\Match\Output;

use Vanderlee\Comprehend\Core\Token;

trait TokenTrait
{
    /**
     * List of callbacks to process for tokens.
     *
     * @var callable
     */
    private $tokenCallback = null;

    /**
     * Add a callback to this match, to be called after parsing is finished and
     * only if this match was part of the matched rules.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function setTokenCallback(callable $callback)
    {
        $this->tokenCallback = $callback;

        return $this;
    }

    /**
     * @return Token
     */
    private function processTokenCallback()
    {
        $children = [];
        /** @var self $success */
        foreach ($this->successes as $success) {
            $children[] = $success->processTokenCallback();
        }

        if ($this->tokenCallback) {
            $tokenCallback = $this->tokenCallback;

            return $tokenCallback($children);
        }
    }

    /**
     * @return Token
     */
    public function getToken()
    {
        return $this->processTokenCallback();
    }
}
