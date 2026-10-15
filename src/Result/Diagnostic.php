<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;

/** Immutable recognition facts with lazily indexed, one-based locations. */
final class Diagnostic
{
    /**
     * @var list<int>|null
     */
    private ?array $lineStarts = null;

    /**
     * @param list<string> $expected
     * @param list<list<string>> $rulePaths
     */
    public function __construct(
        public readonly Source $source,
        public readonly int $byteOffset,
        public readonly array $expected,
        public readonly array $rulePaths,
    ) {
        $source->span($byteOffset, $byteOffset);
    }

    /** Raw current line; byte sources may contain arbitrary bytes. */
    public function excerpt(): string
    {
        $line = $this->location()['line'] - 1;
        $start = $this->lineStarts[$line];
        $end = $this->lineStarts[$line + 1] ?? $this->source->byteLength();
        return rtrim($this->source->sliceBytes($start, $end), "\r\n");
    }

    /**
     * CRLF is one newline; lone CR and LF also start lines. Text columns count code points.
     * @return array{line: int, column: int}
     */
    public function location(): array
    {
        $this->indexLines();

        $low = 0;
        $high = count($this->lineStarts);
        while ($low + 1 < $high) {
            $middle = intdiv($low + $high, 2);
            if ($this->lineStarts[$middle] <= $this->byteOffset) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        $prefix = $this->source->sliceBytes($this->lineStarts[$low], $this->byteOffset);
        $length = $this->source->mode() === SourceMode::UTF8 ? mb_strlen($prefix, 'UTF-8') : strlen($prefix);
        return ['line' => $low + 1, 'column' => $length + 1];
    }

    public function message(): string
    {
        $location = $this->location();
        $expected = implode(' or ', $this->expected);
        return 'Expected ' . $expected . ' at line ' . $location['line']
            . ', column ' . $location['column'] . ' (byte ' . $this->byteOffset . ')';
    }

    private function indexLines(): void
    {
        if ($this->lineStarts !== null) {
            return;
        }

        $this->lineStarts = [0];
        $pendingCr = false;
        for ($start = 0; $start < $this->source->byteLength(); $start += 8192) {
            $chunk = $this->source->sliceBytes($start, min($start + 8192, $this->source->byteLength()));
            $indexMax = strlen($chunk);
            for ($index = 0; $index < $indexMax; ++$index) {
                $byte = $chunk[$index];
                if ($pendingCr) {
                    $this->lineStarts[] = $start + $index + ($byte === "\n" ? 1 : 0);
                    $pendingCr = false;
                    if ($byte === "\n") {
                        continue;
                    }
                }
                if ($byte === "\r") {
                    $pendingCr = true;
                } elseif ($byte === "\n") {
                    $this->lineStarts[] = $start + $index + 1;
                }
            }
        }
        if ($pendingCr) {
            $this->lineStarts[] = $this->source->byteLength();
        }
    }
}
