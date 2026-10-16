<?php

declare(strict_types=1);

namespace Tests\Extensions\Expression;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Expression\ReductionSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('reduction sequences select values lazily without dropping syntax', function (): void {
    $calls = 0;
    $parser = new ReductionSequence(static function (array $parts) use (&$calls): string {
        ++$calls;
        return strtoupper($parts[1]->value());
    }, new Text('('), new Text('a'), new Text(')'));
    $result = $parser->freeze()->parse('(a)');
    $this->assertSame(0, $calls);
    $this->assertSame('A', $result->value());
    $this->assertSame('A', $result->value());
    $this->assertSame(1, $calls);
    $this->assertSame('(a)', $result->rawText());
    $this->assertCount(3, $result->node->children);
    $this->assertFalse($parser->canFlattenPrefix());
    $this->assertFalse($parser->parseAll(new ByteSource('(a'))->success);
});
