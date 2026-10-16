<?php

declare(strict_types=1);

namespace Tests\Unit\src\Parser\Structure;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Extraction\OptionalValue;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('optional values distinguish present null from absence', function (): void {
    $parser = new OptionalValue((new Text('nil'))->map(static fn(string $s): mixed => null), 'missing');
    $this->assertNull($parser->freeze()->parse('nil')->value());
    $this->assertSame('missing', $parser->parseAll(new ByteSource(''))->value());
    $this->assertFalse($parser->parseAll(new ByteSource('nilnil'))->success);
    $this->assertNull((new OptionalValue(new Text('a')))->freeze()->parse('')->value());
});

test('optional defaults preserve object and callable identity without invocation', function (): void {
    $default = static fn(): never => throw new LogicException('must not run');
    $parser = new OptionalValue(new Text('a'), $default);
    $this->assertSame($default, $parser->freeze()->parse('')->value());
    $this->assertSame('a', $parser->freeze()->parse('a')->value());
});

test('optional values preserve progress checks', function (bool $freeze): void {
    $parser = new OptionalValue(new Nothing(), 'missing');
    $this->expectException(LogicException::class);
    if ($freeze) {
        $parser->freeze();
    } else {
        $parser->parseAll(new ByteSource(''));
    }
})->with([false, true]);
