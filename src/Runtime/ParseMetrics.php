<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use Vanderlee\Comprehend\Parser\Parser;

/**
 * Collects bounded profiling counts for parser visits and source positions. ParseSession can
 * record repeated work without introducing recognition memoization.
 */
final class ParseMetrics
{
    private int $entries = 0;
    private int $repeatedPositions = 0;
    /**
     * @var array<string, Parser>
     */
    private array $positions = [];

    public function __construct(private readonly int $maxTrackedPositions = 4096)
    {
        if ($maxTrackedPositions < 1) {
            throw new \InvalidArgumentException('Profile capacity must be positive');
        }
    }

    public function record(Parser $parser, int $offset): void
    {
        ++$this->entries;
        $key = spl_object_id($parser) . ':' . $offset;
        if (isset($this->positions[$key])) {
            ++$this->repeatedPositions;
        } elseif (count($this->positions) < $this->maxTrackedPositions) {
            $this->positions[$key] = $parser;
        }
    }

    /**
     * Repeated positions ignore scope context: they are candidates, not proven cache hits.
     * @return array{parser_entries: int, repeated_positions: int, tracked_positions: int, capacity: int}
     */
    public function snapshot(): array
    {
        return [
            'parser_entries' => $this->entries,
            'repeated_positions' => $this->repeatedPositions,
            'tracked_positions' => count($this->positions),
            'capacity' => $this->maxTrackedPositions,
        ];
    }
}
