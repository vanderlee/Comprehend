<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

final class StubTest extends TestCase
{
    public function testSetterProvidesTheRecognitionTarget(): void
    {
        $stub = new Stub();
        $target = new Text('a');
        $this->assertNull($stub->getParser());
        $this->assertSame($stub, $stub->setParser($target));
        $this->assertSame($target, $stub->getParser());
        $this->assertTrue($stub->parseAll(new ByteSource('a'))->success);
        $this->assertFalse($stub->parseAll(new ByteSource('b'))->success);
    }

    public function testUnresolvedStubCannotRecognize(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Missing parser');

        (new Stub())->parseAll(new ByteSource('a'));
    }
}
