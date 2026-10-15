<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;

final class SuccessTest extends TestCase
{
    public function testConcreteNodesRequireSourceMetadata(): void
    {
        $success = new Success(1);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Concrete nodes require source-bound recognition');

        $success->buildConcreteNode('test');
    }

    public function testValueMappingIsLazyAndCachedIndependentlyOfCommit(): void
    {
        $success = new Success(1);
        $success->setSourceMetadata((new ByteSource('a'))->span(0, 1), null);
        $success->buildConcreteNode('test');
        $calls = 0;
        $success->setValueMapper(static function (ParseResult $result) use (&$calls): string {
            ++$calls;

            return strtoupper($result->rawText());
        });

        $this->assertTrue($success->accepted);
        $this->assertSame(0, $calls);
        $this->assertSame('A', $success->getValue());
        $this->assertSame('A', $success->getValue());
        $this->assertSame(1, $calls);
        $this->assertFalse($success->isCommitted());
    }

    public function testCommitRunsEffectsOnlyOnce(): void
    {
        $success = new Success();
        $success->setSourceMetadata((new ByteSource(''))->span(0, 0), null);
        $calls = 0;
        $success->addCustomCallback(static function () use (&$calls): void {
            ++$calls;
        });

        $success->commit();
        $success->commit();

        $this->assertSame(1, $calls);
        $this->assertTrue($success->isCommitted());
    }
}
