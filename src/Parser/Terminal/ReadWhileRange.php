<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use InvalidArgumentException;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\Utf8Source;

/** Scans an inclusive range using the source mode and scoped case policy. */
final class ReadWhileRange extends ReadRun
{
    private int $lower = 0;
    private ?int $upper = null;

    public function __construct(
        private readonly ?string $first,
        private readonly ?string $last,
        int $min = 0,
        ?int $max = null,
    ) {
        parent::__construct($min, $max);
        if ($first === null && $last === null) {
            throw new InvalidArgumentException('Empty range');
        }
    }

    public function __toString(): string
    {
        return 'readWhileRange(' . ($this->first === null ? '*' : bin2hex($this->first))
            . '..' . ($this->last === null ? '*' : bin2hex($this->last)) . ')';
    }

    protected function prepareScan(Source $source, ParseSession $session): void
    {
        foreach ([$this->first, $this->last] as $bound) {
            if ($bound === null) {
                continue;
            }
            $length = $source->mode() === SourceMode::UTF8
                ? (new Utf8Source($bound))->codePointLength() : strlen($bound);
            if ($length !== 1) {
                throw new InvalidArgumentException('ReadWhileRange requires single-unit bounds');
            }
        }
        $this->lower = $this->first === null ? 0 : $this->ordinal($this->first, $session);
        $this->upper = $this->last === null ? null : $this->ordinal($this->last, $session);
        if ($this->upper !== null && $this->upper < $this->lower) {
            throw new InvalidArgumentException('ReadWhileRange requires ordered bounds');
        }
    }

    private function ordinal(string $character, ParseSession $session): int
    {
        $value = $session->handleCase($character);
        if ($session->source()->mode() === SourceMode::BYTES) {
            return ord($value);
        }
        if (mb_strlen($value, 'UTF-8') !== 1) {
            throw new InvalidArgumentException('Range case mapping must remain one code point');
        }

        return mb_ord($value, 'UTF-8');
    }

    protected function acceptsUnit(Span $unit, ParseSession $session): bool
    {
        $value = $this->ordinal($unit->rawText(), $session);

        return $value >= $this->lower && ($this->upper === null || $value <= $this->upper);
    }

    protected function regexUnitPattern(Source $source): string
    {
        $upper = $this->upper ?? ($source->mode() === SourceMode::UTF8 ? 0x10ffff : 0xff);

        return '[\\x{' . dechex($this->lower) . '}-\\x{' . dechex($upper) . '}]';
    }
}
