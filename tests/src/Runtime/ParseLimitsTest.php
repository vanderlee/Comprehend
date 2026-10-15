<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Runtime\ParseLimits;

abstract class ParseLimitsTest extends TestCase
{
    public static function invalidLimits(): array
    {
        return [[0, null], [-1, null], [null, 0], [null, -1]];
    }
}

uses(ParseLimitsTest::class);

test('limits default to unlimited and retain positive bounds', function (): void {
    $unlimited = new ParseLimits();
    $bounded = new ParseLimits(2, 10);

    $this->assertNull($unlimited->maxDepth);
    $this->assertNull($unlimited->maxWork);
    $this->assertSame(2, $bounded->maxDepth);
    $this->assertSame(10, $bounded->maxWork);
});

test('nonpositive limits are rejected', function (?int $depth, ?int $work): void {
    $this->expectException(InvalidArgumentException::class);

    new ParseLimits($depth, $work);
})->with(ParseLimitsTest::invalidLimits());
