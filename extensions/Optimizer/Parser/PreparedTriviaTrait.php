<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Optimizer\Parser;

use Vanderlee\Comprehend\Directive\Trivia as OrdinaryTrivia;

/** @internal Only prepared snapshots specialize the session's automatic trivia wrapper. */
trait PreparedTriviaTrait
{
    private bool $triviaPrepared = false;

    public function prepareTriviaRecognition(): void
    {
        $this->triviaPrepared = true;
    }

    public function trivia(): OrdinaryTrivia
    {
        $ordinary = parent::trivia();
        if (!$this->triviaPrepared) {
            return $ordinary;
        }
        $prepared = Trivia::from($ordinary);
        $prepared->prepareRecognition();

        return $prepared;
    }
}
