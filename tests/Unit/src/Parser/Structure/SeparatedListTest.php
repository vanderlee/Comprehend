<?php

declare(strict_types=1);

namespace Tests\Src\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('separated list accepts only the configured boundaries', function (
    string $input,
    bool $leading,
    bool $trailing,
    bool $accepted,
): void {
    $parser = new SeparatedList(new Text('a'), new Text(','), allowLeading: $leading, allowTrailing: $trailing);
    $this->assertSame($accepted, $parser->parseAll(new ByteSource($input))->success);
})->with([
    ['', false, false, true],
    ['a', false, false, true],
    ['a,a', false, false, true],
    [',a', false, false, false],
    ['a,', false, false, false],
    [',a', true, false, true],
    ['a,', false, true, true],
    [',a,a,', true, true, true],
    [',', true, true, false],
    ['a,,a', true, true, false],
]);

test('separated list bounds count items and leave excess input', function (): void {
    $parser = new SeparatedList(new Text('a'), new Text(','), 2, 2, allowTrailing: true);
    $this->assertFalse($parser->parseAll(new ByteSource('a'))->success);
    $this->assertSame(['a', 'a'], $parser->parseAll(new ByteSource('a,a,'))->value());
    $prefix = $parser->matchPrefix(new ByteSource('a,a,a'));
    $this->assertSame('a,a,', $prefix->rawText());
    $this->assertFalse($parser->parseAll(new ByteSource('a,a,a'))->success);
});

test('separated list rejects invalid bounds', function (int $min, ?int $max): void {
    $this->expectException(InvalidArgumentException::class);
    new SeparatedList(new Text('a'), new Text(','), $min, $max);
})->with([[-1, null], [2, 1], [0, -1]]);

test('zero maximum attempts no operands even when they are nullable', function (): void {
    $parser = new SeparatedList(new Nothing(), new Nothing(), max: 0, allowLeading: true, allowTrailing: true);
    $result = $parser->freeze()->matchPrefix(new ByteSource(','));
    $this->assertSame('', $result->rawText());
    $this->assertSame([], $result->value());
});

test('nullable items are rejected both by freezing and direct recognition', function (bool $freeze): void {
    $parser = new SeparatedList(new Nothing(), new Text(','));
    $this->expectException(LogicException::class);
    if ($freeze) {
        $parser->freeze();
    } else {
        $parser->matchPrefix(new ByteSource(''));
    }
})->with([false, true]);

test('zero width separators are safe when items consume', function (): void {
    $result = (new SeparatedList(new Text('a'), new Nothing()))->freeze()->parseAll(new ByteSource('aaa'));
    $this->assertSame(['a', 'a', 'a'], $result->value());
});

test('failed pair rolls back its separator and spacing', function (bool $trailing, string $raw): void {
    $parser = (new SeparatedList(new Text('a'), new Text(','), allowTrailing: $trailing))->spacing(new Text(' '));
    $result = $parser->matchPrefix(new ByteSource('a , x'));
    $this->assertSame($raw, $result->rawText());
    $this->assertNull($result->diagnostic);
})->with([[false, 'a'], [true, 'a ,']]);

test('required items retain their useful diagnostic', function (): void {
    $result = (new SeparatedList(new Text('abc'), new Text(','), min: 2))->parseAll(new ByteSource('abc,abx'));
    $this->assertFalse($result->success);
    $this->assertSame(6, $result->diagnostic->byteOffset);
    $this->assertContains('"abc"', $result->diagnostic->expected);
});
