<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\SemanticProjection;
use Vanderlee\Comprehend\Source\ByteSource;

final class SemanticProjectionTest extends TestCase
{
    public function testNullMappingResultsAreCached(): void
    {
        $calls = 0;
        $projection = new SemanticProjection(static function () use (&$calls): mixed {
            ++$calls;

            return null;
        });
        $result = (new Text('a'))->parseAll(new ByteSource('a'));

        $this->assertNull($projection->value($result));
        $this->assertNull($projection->value($result));
        $this->assertSame(1, $calls);
        $this->assertSame('a', (new SemanticProjection(null))->value($result));
    }

    public function testMappingFailuresAreRetainedWithoutRetrying(): void
    {
        $calls = 0;
        $error = new RuntimeException('mapping failed');
        $projection = new SemanticProjection(static function () use (&$calls, $error): never {
            ++$calls;
            throw $error;
        });
        $result = (new Text('a'))->parseAll(new ByteSource('a'));

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $projection->value($result);
                $this->fail('Expected the retained mapping failure');
            } catch (RuntimeException $caught) {
                $this->assertSame($error, $caught);
            }
        }
        $this->assertSame(1, $calls);
    }
}
