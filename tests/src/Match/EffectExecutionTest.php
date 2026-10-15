<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use PHPUnit\Framework\TestCase;
use LogicException;
use RuntimeException;
use Vanderlee\Comprehend\Match\EffectExecution;

final class EffectExecutionTest extends TestCase
{
    public function testRunningSequenceIsNotCommittedAndSharedTraversalDoesNotReplay(): void
    {
        $execution = new EffectExecution();
        $calls = 0;
        $this->assertFalse($execution->isCompleted());
        $execution->run(function () use ($execution, &$calls): void {
            ++$calls;
            $this->assertFalse($execution->isCompleted());
            $execution->run(function (): void {
                $this->fail('Active sequence replayed');
            });
            try {
                $execution->assertNotRunning();
                $this->fail('Expected recursive commit rejection');
            } catch (LogicException $error) {
                $this->assertSame('Recursive effect commit', $error->getMessage());
            }
        });
        $execution->run(function (): void {
            $this->fail('Completed sequence replayed');
        });
        $execution->assertNotRunning();
        $this->assertSame(1, $calls);
        $this->assertTrue($execution->isCompleted());
    }

    public function testFailureIsRetainedWithoutRetry(): void
    {
        $execution = new EffectExecution();
        $failure = new RuntimeException('failed');
        $calls = 0;
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $execution->run(function () use (&$calls, $failure): void {
                    ++$calls;
                    throw $failure;
                });
                $this->fail('Expected original failure');
            } catch (RuntimeException $error) {
                $this->assertSame($failure, $error);
            }
        }
        $this->assertSame(1, $calls);
        $this->assertFalse($execution->isCompleted());
    }
}
