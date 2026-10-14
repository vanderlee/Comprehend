<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use Vanderlee\Comprehend\Parser\Parser;

/** Optional bounded profiling, not a recognition-result cache. */
final class ParseMetrics
{
    private int $entries = 0;
    private int $repeatedPositions = 0;
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

    /** Repeated positions ignore scope context: they are candidates, not proven cache hits. */
    public function snapshot(): array
    {
        return [
            'parser_entries' => $this->entries,
            'repeated_positions' => $this->repeatedPositions,
            'tracked_positions' => count($this->positions),
            'capacity' => $this->maxTrackedPositions
        ];
    }
}
