<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\Implementation;
use Vanderlee\Comprehend\Parser\Terminal\Text;

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
}
