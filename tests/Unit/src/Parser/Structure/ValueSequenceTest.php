<?php

declare(strict_types=1);

namespace Tests\Src\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Extensions\Binding\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('value sequence selects operand text while preserving its complete span', function (
    int $position,
    string $value,
): void {
    $parser = new ValueSequence($position, new Text('('), new Text('body'), new Text(')'));
    $result = $parser->parseAll(new ByteSource('(body)'));
    $this->assertTrue($result->success);
    $this->assertSame($value, $result->value());
    $this->assertSame('(body)', $result->rawText());
    $this->assertCount(3, $result->node->children);
    $this->assertFalse($parser->canFlattenPrefix());
})->with([[0, '('], [1, 'body'], [2, ')']]);

test('value sequence rejects invalid selected positions', function (int $position): void {
    $this->expectException(InvalidArgumentException::class);
    new ValueSequence($position, new Text('a'));
})->with([-1, 1]);

test('mutating away the selected position fails explicitly', function (bool $freeze): void {
    $parser = new ValueSequence(1, new Text('a'), new Text('b'));
    unset($parser[1]);
    $this->expectException(LogicException::class);
    if ($freeze) {
        $parser->freeze();
    } else {
        $parser->parseAll(new ByteSource('a'));
    }
})->with([false, true]);

test('value sequence supports empty selected text and explicit mapping overrides', function (): void {
    $parser = new ValueSequence(1, new Text('('), new Nothing(), new Text(')'));
    $this->assertSame('', $parser->parseAll(new ByteSource('()'))->value());
    $parser->mapValue(static fn(ParseResult $result): int => strlen($result->rawText()));
    $this->assertSame(2, $parser->parseAll(new ByteSource('()'))->value());
});

test('value sequence uses ordinary sequence failure diagnostics', function (): void {
    $parser = new ValueSequence(1, new Text('('), new Text('body'), new Text(')'));
    $result = $parser->parseAll(new ByteSource('(body'));
    $this->assertFalse($result->success);
    $this->assertNull($result->value());
    $this->assertSame(5, $result->diagnostic->byteOffset);
    $this->assertContains('")"', $result->diagnostic->expected);
});

test('value sequence snapshots detach selected and unselected dependencies', function (): void {
    $body = new Sequence(new Text('a'));
    $close = new Sequence(new Text(')'));
    $parser = new ValueSequence(1, new Text('('), $body, $close);
    $frozen = $parser->freeze();
    $body->add(new Text('b'));
    $close->add(new Text('!'));
    $this->assertSame('a', $frozen->parse('(a)')->value());
    $this->assertFalse($parser->parseAll(new ByteSource('(a)'))->success);
    $this->assertSame('ab', $parser->parseAll(new ByteSource('(ab)!'))->value());
});
