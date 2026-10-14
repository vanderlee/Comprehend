<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Result;

use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Source;
/** Immutable recognition facts with lazily indexed, one-based locations. */
final class Diagnostic
{
    private ?array $lineStarts = null;
    public function __construct(public readonly Source $source, public readonly int $byteOffset, public readonly array $expected, public readonly array $rulePaths)
    {
        $source->span($byteOffset, $byteOffset);
    }
    /** CRLF is one newline; lone CR and LF also start lines. Text columns count code points. */
    public function location(): array
    {
        if ($this->lineStarts === null) {
            $this->lineStarts = [0];
            preg_match_all(
                '/\r\n|\r|\n/',
                $this->source->sliceBytes(0, $this->source->byteLength()),
                $matches,
                PREG_OFFSET_CAPTURE,
            );
            foreach ($matches[0] as [$newline, $offset]) {
                $this->lineStarts[] = $offset + strlen($newline);
            }
        }
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
        return [
            'line' => $low + 1,
            'column' => 1 + ($this->source->mode() === SourceMode::UTF8 ? mb_strlen($prefix, 'UTF-8') : strlen($prefix)),
        ];
    }
    /** Raw current line; byte sources may contain arbitrary bytes. */
    public function excerpt(): string
    {
        $line = $this->location()['line'] - 1;
        $start = $this->lineStarts[$line];
        $end = $this->lineStarts[$line + 1] ?? $this->source->byteLength();
        return rtrim($this->source->sliceBytes($start, $end), "\r\n");
    }
    public function message(): string
    {
        $location = $this->location();
        return 'Expected ' . implode(' or ', $this->expected) . ' at line ' . $location['line'] . ', column ' . $location['column'] . ' (byte ' . $this->byteOffset . ')';
    }
}
