<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

final class ParseSessionTest extends TestCase
{
    public function testDiagnosticsMergeTiesAndCanBeRestoredAfterAProbe(): void
    {
        $session = new ParseSession(new ByteSource('abc'));
        $session->pushRule('root');
        $session->recordFailure(1, 'a');
        $session->recordFailure(1, 'b');
        $saved = $session->diagnosticState();
        $session->recordFailure(2, 'c');
        $session->restoreDiagnostics($saved);
        $session->popRule();

        $this->assertSame(1, $session->farthestFailure());
        $this->assertSame(['a', 'b'], $session->diagnostic()->expected);
        $this->assertSame([['root']], $session->diagnostic()->rulePaths);
        $this->assertSame(2, $session->failureRevision());
    }

    public function testFailedWorkChargeDoesNotConsumeTheRemainingBudget(): void
    {
        $session = new ParseSession(new ByteSource('a'), limits: new ParseLimits(maxWork: 2));
        $session->consumeWork(1);

        try {
            $session->consumeWork(2, 1);
            $this->fail('Expected work exhaustion');
        } catch (ParseLimitException $error) {
            $this->assertSame('work', $error->limit);
            $this->assertSame(1, $error->byteOffset);
        }

        $this->assertSame(1, $session->workUsed());
        $this->assertSame('a', $session->readSourceUnit(0)->rawText());
        $this->assertSame(2, $session->workUsed());
    }
}
