<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;

/**
 * Describes a recognition failure with expectations, rule paths and a byte offset. Lazily
 * indexes source lines to provide readable locations and excerpts for ParseResult and grammar
 * errors.
 */
final class Diagnostic
{
    /**
     * @var non-empty-list<int>|null
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
        $lineStarts = $this->indexLines();
        $start = $lineStarts[$line];
        $end = $lineStarts[$line + 1] ?? $this->source->byteLength();

        return rtrim($this->source->sliceBytes($start, $end), "\r\n");
    }

    /**
     * CRLF is one newline; lone CR and LF also start lines. Text columns count code points.
     * @return array{line: int, column: int}
     */
    public function location(): array
    {
        $lineStarts = $this->indexLines();

        $low = 0;
        $high = count($lineStarts);
        while ($low + 1 < $high) {
            $middle = intdiv($low + $high, 2);
            if ($lineStarts[$middle] <= $this->byteOffset) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        $prefix = $this->source->sliceBytes($lineStarts[$low], $this->byteOffset);
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

    /** @return non-empty-list<int> */
    private function indexLines(): array
    {
        if ($this->lineStarts !== null) {
            return $this->lineStarts;
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

        return $this->lineStarts;
    }
}
