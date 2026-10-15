<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Runtime\ParseLimits;

final class ParseLimitsTest extends TestCase
{
    public function testLimitsDefaultToUnlimitedAndRetainPositiveBounds(): void
    {
        $unlimited = new ParseLimits();
        $bounded = new ParseLimits(2, 10);

        $this->assertNull($unlimited->maxDepth);
        $this->assertNull($unlimited->maxWork);
        $this->assertSame(2, $bounded->maxDepth);
        $this->assertSame(10, $bounded->maxWork);
    }

    /** @dataProvider invalidLimits */
    public function testNonpositiveLimitsAreRejected(?int $depth, ?int $work): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParseLimits($depth, $work);
    }

    public static function invalidLimits(): array
    {
        return [[0, null], [-1, null], [null, 0], [null, -1]];
    }
}
