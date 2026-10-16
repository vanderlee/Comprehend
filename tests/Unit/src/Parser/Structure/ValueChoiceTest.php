<?php

declare(strict_types=1);

namespace Tests\Src\Parser\Structure;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Binding\ValueChoice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('value choice forwards selected nulls and respects ranked selection', function (): void {
    $short = (new Text('a'))->map(static fn(string $text): ?int => null);
    $long = (new Text('ab'))->map(static fn(string $text): int => 2);
    $parser = new ValueChoice($short, $long);
    $this->assertNull($parser->matchPrefix(new ByteSource('ab'))->value());
    $parser->preferLongest();
    $this->assertSame(2, $parser->freeze()->parse('ab')->value());
    $this->assertFalse($parser->canFlatten());
});
