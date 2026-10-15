<?php

declare(strict_types=1);

namespace Tests\features\semantics;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend;

final class ResultFailureCachingTest extends TestCase
{
    public function testCollectionAndDefinitionProcessorFailuresAreRetained(): void
    {
        foreach (['collection', 'definition'] as $mode) {
            $calls = 0;
            $failure = new RuntimeException('Result processing failed');
            $processor = static function () use (&$calls, $failure): never {
                ++$calls;
                throw $failure;
            };
            $parser = Comprehend::text('a');
            if ($mode === 'collection') {
                $parser = $parser->setResult('item', $processor);
            } else {
                $definition = new Definition($parser);
                $definition->addProcessor('item', $processor);
                $parser = $definition->build();
            }

            $result = $parser->parse('a');
            $this->assertTrue($result->success);
            $this->assertSame(0, $calls);
            for ($attempt = 0; $attempt < 2; ++$attempt) {
                try {
                    $result->results();
                    $this->fail('Expected the retained processing failure');
                } catch (RuntimeException $error) {
                    $this->assertSame($failure, $error);
                }
            }
            $this->assertSame(1, $calls);
        }
    }
}
