<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;

final class SingleUnitSource implements Source
{
    public int $spanCalls = 0;
    public int $unitReads = 0;
    public ?Span $lastUnit = null;
    public bool $reuseUnits = false;
    private Source $inner;

    public function __construct(Source $inner)
    {
        $this->inner = $inner;
    }

    public function mode(): SourceMode
    {
        return $this->inner->mode();
    }

    public function byteLength(): int
    {
        return $this->inner->byteLength();
    }

    public function sliceBytes(int $start, int $end): string
    {
        return $this->inner->sliceBytes($start, $end);
    }

    public function span(int $start, int $end): Span
    {
        ++$this->spanCalls;

        return new Span($this, $start, $end);
    }

    public function readUnit(int $byteOffset): ?Span
    {
        ++$this->unitReads;
        if ($this->reuseUnits && $this->lastUnit?->start === $byteOffset) {
            return $this->lastUnit;
        }
        $unit = $this->inner->readUnit($byteOffset);

        return $this->lastUnit = $unit === null ? null : $this->span($unit->start, $unit->end);
    }

    public function isUnitBoundary(int $byteOffset): bool
    {
        return $this->inner->isUnitBoundary($byteOffset);
    }
}
