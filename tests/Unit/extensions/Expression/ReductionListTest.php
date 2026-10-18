<?php

declare(strict_types=1);

namespace Tests\Extensions\Expression;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Expression\Parser\ReductionList;
use Vanderlee\Comprehend\Parser\Terminal\Text;

uses(TestCase::class);

test('reduction lists receive selected separators but not automatic trivia', function (): void {
    $calls = 0;
    $parser = (new ReductionList(static function (array $parts) use (&$calls): array {
        ++$calls;
        return array_map(static fn($part): string => $part->rawText(), $parts);
    }, new Text('a'), new Text(',')))->spacing(new Text(' '));
    $result = $parser->freeze()->matchPrefix('a , a , x');
    $this->assertSame(0, $calls);
    $this->assertSame(['a', ',', 'a'], $result->value());
    $this->assertSame(['a', ',', 'a'], $result->value());
    $this->assertSame(1, $calls);
    $this->assertSame('a , a', $result->rawText());
    $this->assertCount(5, $result->node->children);
});
