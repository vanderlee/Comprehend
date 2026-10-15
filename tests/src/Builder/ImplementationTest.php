<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Implementation;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

final class ImplementationTest extends TestCase
{
    public function testFactoryIsDeferredAndMaterializedOncePerInstance(): void
    {
        $calls = 0;
        $definition = new Definition(static function (string $text) use (&$calls): Text {
            ++$calls;

            return new Text($text);
        });
        $implementation = new Implementation($definition, ['a']);

        $this->assertSame(0, $calls);
        $this->assertSame($implementation->graphTarget(), $implementation->graphTarget());
        $this->assertSame(1, $calls);
    }

    public function testFactoryMustReturnATypedParser(): void
    {
        $implementation = new Implementation(new Definition(static fn(): string => 'a'));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Definition factory must return Comprehend or Parser');

        $implementation->graphTarget();
    }

    public function testProcessorFailureRetainsEarlierWorkWithoutRunningLaterProcessors(): void
    {
        $events = [];
        $failure = new RuntimeException('Processor failed');
        $definition = new Definition((new Text('a'))->setResult('item'));
        $definition->addProcessor('first', static function (array $values) use (&$events): string {
            $events[] = 'first';

            return $values['item'];
        });
        $definition->addProcessor('failed', static function () use (&$events, $failure): never {
            $events[] = 'failed';
            throw $failure;
        });
        $definition->addProcessor('later', static function () use (&$events): string {
            $events[] = 'later';

            return 'unused';
        });
        $result = $definition->implementation()->parseAll(new ByteSource('a'));

        $this->assertSame([], $events);
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $result->results();
                $this->fail('Expected the retained processor failure');
            } catch (RuntimeException $error) {
                $this->assertSame($failure, $error);
            }
        }

        $this->assertSame(['first', 'failed'], $events);
        $this->assertFalse($result->isCommitted());
    }
}
