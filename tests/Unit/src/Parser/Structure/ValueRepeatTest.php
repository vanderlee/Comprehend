<?php

declare(strict_types=1);

namespace Tests\Unit\src\Parser\Structure;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Binding\ValueRepeat;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('value repetition keeps item values including null and nested arrays', function (): void {
    $item = (new Text('a'))->map(static fn(string $s): array => [null, 1]);
    $parser = new ValueRepeat($item, 0, 2);
    $result = $parser->freeze()->parse('aa');
    $this->assertSame([[null, 1], [null, 1]], $result->value());
    $this->assertSame('aa', $result->rawText());
    $this->assertCount(2, $result->node->children);
    $this->assertSame([], $parser->parseAll(new ByteSource(''))->value());
    $this->assertFalse($parser->parseAll(new ByteSource('aaa'))->success);
});

test('value repetition rejects invalid bounds', function (int $min, ?int $max): void {
    $this->expectException(InvalidArgumentException::class);
    new ValueRepeat(new Text('a'), $min, $max);
})->with([[-1, null], [2, 1]]);

test('value repetition preserves nullable child safety', function (bool $freeze): void {
    $parser = new ValueRepeat(new Nothing());
    $this->expectException(LogicException::class);
    if ($freeze) {
        $parser->freeze();
    } else {
        $parser->parseAll(new ByteSource(''));
    }
})->with([false, true]);

test('zero maximum skips nullable children and maps to an empty list', function (): void {
    $parser = new ValueRepeat(new Nothing(), 0, 0);
    $this->assertSame([], $parser->freeze()->parse('')->value());
});
