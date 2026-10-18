<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

use LogicException;
use Throwable;

/** @internal Owns execution-once transitions and retains the original failure. */
final class EffectExecution
{
    private EffectState $state = EffectState::Pending;
    private ?Throwable $error = null;

    public function assertNotRunning(): void
    {
        if ($this->state === EffectState::Running) {
            throw new LogicException('Recursive effect commit');
        }
    }

    public function isCompleted(): bool
    {
        return $this->state === EffectState::Completed;
    }

    public function run(callable $effects): void
    {
        if ($this->error !== null) {
            throw $this->error;
        }
        // Shared children are traversed once. Explicit commit rejects recursion separately.
        if ($this->state !== EffectState::Pending) {
            return;
        }
        $this->state = EffectState::Running;
        try {
            $effects();
            $this->state = EffectState::Completed;
        } catch (Throwable $error) {
            $this->error = $error;
            $this->state = EffectState::Failed;
            throw $error;
        }
    }
}
