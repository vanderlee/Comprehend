<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use DomainException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

final class ParseSessionTest extends TestCase
{
    public function testInvalidChoicePolicyDoesNotChangeTheActiveScope(): void
    {
        $session = new ParseSession(new ByteSource(''));
        $session->pushPreference(ChoicePolicy::LONGEST);

        try {
            $session->pushPreference('invalid');
            $this->fail('Expected an invalid choice policy');
        } catch (DomainException $error) {
            $this->assertSame('Invalid preference `invalid`', $error->getMessage());
        }

        $this->assertSame(ChoicePolicy::LONGEST, $session->getPreference());
        $session->popPreference();
        $this->assertSame(ChoicePolicy::FIRST, $session->getPreference());
    }

    public function testPoppingAnExhaustedCaseScopeIsRejected(): void
    {
        $session = new ParseSession(new ByteSource(''));
        $this->assertTrue($session->popCaseSensitivity());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Case sensitivity scope is empty');

        $session->popCaseSensitivity();
    }

    public function testReadingAnExhaustedPreferenceScopeIsRejected(): void
    {
        $session = new ParseSession(new ByteSource(''));
        $session->popPreference();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Choice preference scope is empty');

        $session->getPreference();
    }

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

    public function testNearerFailureAdvancesRevisionWithoutReplacingFarthestDetails(): void
    {
        $session = new ParseSession(new ByteSource('abc'));
        $session->recordFailure(2, 'farther', [['root', 'farther']]);
        $session->recordFailure(1, 'nearer', [['root', 'nearer']]);

        $this->assertSame(2, $session->failureRevision());
        $this->assertSame(2, $session->farthestFailure());
        $this->assertSame(['farther'], $session->diagnostic()->expected);
        $this->assertSame([['root', 'farther']], $session->diagnostic()->rulePaths);
    }

    public function testRejectedEntryDoesNotRetainParserOrDepthState(): void
    {
        $session = new ParseSession(new ByteSource('a'), limits: new ParseLimits(maxDepth: 1));
        $outer = new Text('a');
        $inner = new Text('a');
        $session->enterParser($outer, 0);

        try {
            $session->enterParser($inner, 0);
            $this->fail('Expected depth exhaustion');
        } catch (ParseLimitException $error) {
            $this->assertSame('depth', $error->limit);
        }

        $session->leaveParser($outer, 0);
        $session->enterParser($inner, 0);
        $session->leaveParser($inner, 0);

        $this->assertSame(2, $session->workUsed());
    }
}
