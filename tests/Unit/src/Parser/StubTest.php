<?php

declare(strict_types=1);

namespace Tests\Src\Parser;

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('setter provides the recognition target', function (): void {
    $stub = new Stub();
    $target = new Text('a');
    $this->assertNull($stub->getParser());
    $this->assertSame($stub, $stub->setParser($target));
    $this->assertSame($target, $stub->getParser());
    $this->assertTrue($stub->parseAll(new ByteSource('a'))->success);
    $this->assertFalse($stub->parseAll(new ByteSource('b'))->success);
});

test('unresolved stub cannot recognize', function (): void {
    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage('Missing parser');

    (new Stub())->parseAll(new ByteSource('a'));
});
