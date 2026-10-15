<?php

declare(strict_types=1);

namespace Tests\Src\Runtime;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseMetrics;

final class ParseMetricsTest extends TestCase
{
    public function testPositionTrackingIsBoundedButEntriesStillAccumulate(): void
    {
        $metrics = new ParseMetrics(1);
        $parser = new Text('a');
        $metrics->record($parser, 0);
        $metrics->record($parser, 0);
        $metrics->record($parser, 1);
        $metrics->record($parser, 1);

        $this->assertSame([
            'parser_entries' => 4,
            'repeated_positions' => 1,
            'tracked_positions' => 1,
            'capacity' => 1,
        ], $metrics->snapshot());
    }

    public function testCapacityMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParseMetrics(0);
    }
}
