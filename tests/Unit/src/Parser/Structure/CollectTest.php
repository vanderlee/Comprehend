<?php

declare(strict_types=1);

namespace Tests\Src\Parser\Structure;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Binding\Collect;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('collect retains direct operand values and nesting in frozen graphs', function (): void {
    $parser = new Collect(new Text('a'), new Collect(new Text('b'), new Text('c')));
    $result = $parser->freeze()->parse(new ByteSource('abc'));
    $this->assertSame(['a', ['b', 'c']], $result->value());
    $this->assertSame('abc', $result->rawText());
    $this->assertCount(2, $result->node->children);
    $this->assertFalse($parser->canFlattenPrefix());
});

test('collect rejects an empty operand sequence', function (): void {
    $this->expectException(InvalidArgumentException::class);
    new Collect();
});
