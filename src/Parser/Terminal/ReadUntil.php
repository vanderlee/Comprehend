<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Closure;
use InvalidArgumentException;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Source\Utf8Source;

/** Stops before a literal delimiter or a raw unit accepted by a pure predicate. */
final class ReadUntil extends ReadRun
{
    /** @var Closure(string): bool|string */
    private readonly Closure|string $delimiter;
    /** @var list<string> */
    private array $characters = [];

    /** @param string|callable(string): bool $delimiter Strings always denote literal delimiters. */
    public function __construct(string|callable $delimiter, int $min = 0, ?int $max = null)
    {
        parent::__construct($min, $max);
        if ($delimiter === '') {
            throw new InvalidArgumentException('ReadUntil requires a nonempty delimiter');
        }
        $this->delimiter = is_string($delimiter)
            ? $delimiter : static fn(string $unit): bool => $delimiter($unit);
    }

    public function __toString(): string
    {
        return is_string($this->delimiter) ? 'readUntil(' . bin2hex($this->delimiter) . ')' : 'readUntil(predicate)';
    }

    protected function prepareScan(Source $source, ParseSession $session): void
    {
        if (!is_string($this->delimiter)) {
            return;
        }
        if ($source->mode() === SourceMode::UTF8) {
            new Utf8Source($this->delimiter);
            $this->characters = mb_str_split($this->delimiter, 1, 'UTF-8');
        } else {
            $this->characters = str_split($this->delimiter);
        }
    }

    protected function acceptsUnit(Span $unit, ParseSession $session): bool
    {
        if ($this->delimiter instanceof Closure) {
            return !($this->delimiter)($unit->rawText());
        }
        if ($session->isCaseSensitive()) {
            $end = $unit->start + strlen($this->delimiter);
            if ($end > $unit->source->byteLength()) {
                return true;
            }
            $candidate = $unit->source->sliceBytes($unit->start, $end);
            $session->consumeWork(strlen($candidate), $unit->start);

            return $candidate !== $this->delimiter;
        }
        $candidate = $unit;
        foreach ($this->characters as $index => $character) {
            if ($index > 0) {
                $candidate = $session->readSourceUnit($candidate->end);
            }
            if (
                $candidate === null
                || $session->handleCase($candidate->rawText()) !== $session->handleCase($character)
            ) {
                return true;
            }
        }

        return false;
    }

    protected function regexUnitPattern(Source $source): ?string
    {
        return is_string($this->delimiter) ? '(?!' . preg_quote($this->delimiter, '~') . ').' : null;
    }

    protected function chargeRegexLookahead(
        Source $source,
        int $start,
        int $end,
        bool $probe,
        ParseSession $session,
    ): void {
        if (!is_string($this->delimiter)) {
            return;
        }
        $length = strlen($this->delimiter);
        $lastCandidate = $source->byteLength() - $length;
        if ($lastCandidate < $start) {
            return;
        }
        // Count inspected unit starts whose complete delimiter-sized window fits the source.
        $prefixEnd = min($end, $lastCandidate + 1);
        while (!$source->isUnitBoundary($prefixEnd)) {
            ++$prefixEnd;
        }
        $prefix = $source->sliceBytes($start, $prefixEnd);
        $candidates = $source->mode() === SourceMode::UTF8 ? mb_strlen($prefix, 'UTF-8') : strlen($prefix);
        if ($probe && $end < $source->byteLength() && $end <= $lastCandidate) {
            ++$candidates;
        }
        $session->consumeWork($candidates * $length, $start);
    }
}
