<?php

declare(strict_types=1);

namespace Tests\Extensions\Expression;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Extensions\Expression\Parser\ValueChoice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;

uses(TestCase::class);

test('value choices keep only the selected lazy value under first and ranked policies', function (): void {
    $short = (new Text('a'))->mapValue(static fn(): int => 1);
    $long = (new Text('ab'))->mapValue(static fn(): int => 2);
    $choice = new ValueChoice($short, $long);
    $this->assertSame(1, $choice->freeze()->matchPrefix('ab')->value());
    $this->assertSame(2, $choice->preferLongest()->freeze()->parse('ab')->value());
    $this->assertSame(1, $choice->preferShortest()->freeze()->matchPrefix('ab')->value());
    $choice->mapValue(static fn(ParseResult $result): string => $result->rawText());
    $this->assertSame('a', $choice->freeze()->parse('a')->value());
    $this->assertFalse($choice->canFlatten());
    $bad = (new Text('bad'))->mapValue(static function (): never {
        throw new RuntimeException('Unselected mapper');
    });
    $this->assertSame(1, (new ValueChoice($bad, $short))->freeze()->parse('a')->value());
});
