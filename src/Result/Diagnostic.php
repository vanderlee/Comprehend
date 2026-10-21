<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use JsonSerializable;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\SourceMode;
use Vanderlee\Comprehend\Source\Span;

/**
 * Describes a recognition failure with expectations, rule paths and a byte offset. Lazily
 * scans the source prefix and current line to provide readable locations and excerpts for
 * ParseResult and grammar errors.
 */
final class Diagnostic implements JsonSerializable
{
    /** @var array{line: int, column: int}|null */
    private ?array $locationCache = null;
    private int $lineStart = 0;
    private ?int $lineEnd = null;

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
        Span::assertByteOffset($source, $byteOffset);
    }

    /** Raw current line; byte sources may contain arbitrary bytes. */
    public function excerpt(): string
    {
        $this->location();
        $end = $this->lineEnd ??= $this->findLineEnd();

        // An offset between CR and LF still belongs to the preceding line.
        return rtrim($this->source->sliceBytes($this->lineStart, $end), "\r\n");
    }

    /**
     * CRLF is one newline; lone CR and LF also start lines. Text columns count code points.
     * @return array{line: int, column: int}
     */
    public function location(): array
    {
        if ($this->locationCache !== null) {
            // Cached coordinates must not make a closed snapshot readable again.
            $this->source->sliceBytes($this->byteOffset, $this->byteOffset);

            return $this->locationCache;
        }
        [$line, $start] = $this->findLineStart();

        $prefix = $this->source->sliceBytes($start, $this->byteOffset);
        $length = $this->source->mode() === SourceMode::UTF8 ? mb_strlen($prefix, 'UTF-8') : strlen($prefix);
        $this->lineStart = $start;

        return $this->locationCache = ['line' => $line, 'column' => $length + 1];
    }

    public function message(): string
    {
        $location = $this->location();
        $expected = implode(' or ', $this->expected);

        return 'Expected ' . $expected . ' at line ' . $location['line']
            . ', column ' . $location['column'] . ' (byte ' . $this->byteOffset . ')';
    }

    /**
     * Source details are opt-in: locations scan the prefix, and excerpts also read the current line.
     * @return array{byteOffset: int, expected: list<string>, rulePaths: list<list<string>>,
     *     location?: array{line: int, column: int}, excerpt?: string, excerptEncoding?: 'utf8'|'base64'}
     */
    public function toArray(bool $includeLocation = false, bool $includeExcerpt = false): array
    {
        $data = [
            'byteOffset' => $this->byteOffset,
            'expected' => $this->expected,
            'rulePaths' => $this->rulePaths,
        ];
        if ($includeLocation) {
            $data['location'] = $this->location();
        }
        if ($includeExcerpt) {
            $excerpt = $this->excerpt();
            $utf8 = mb_check_encoding($excerpt, 'UTF-8');
            $data['excerpt'] = $utf8 ? $excerpt : base64_encode($excerpt);
            $data['excerptEncoding'] = $utf8 ? 'utf8' : 'base64';
        }

        return $data;
    }

    /** @return array{byteOffset: int, expected: list<string>, rulePaths: list<list<string>>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson(bool $includeLocation = false, bool $includeExcerpt = false): string
    {
        return json_encode($this->toArray($includeLocation, $includeExcerpt), JSON_THROW_ON_ERROR);
    }

    /** @return array{int, int} One-based line number and its starting byte offset. */
    private function findLineStart(): array
    {
        $line = 1;
        $lineStart = 0;
        $pendingCr = false;
        for ($start = 0; $start < $this->byteOffset; $start += 8192) {
            $chunk = $this->source->sliceBytes($start, min($start + 8192, $this->byteOffset));
            $indexMax = strlen($chunk);
            for ($index = 0; $index < $indexMax; ++$index) {
                $byte = $chunk[$index];
                if ($pendingCr) {
                    ++$line;
                    $lineStart = $start + $index + ($byte === "\n" ? 1 : 0);
                    $pendingCr = false;
                    if ($byte === "\n") {
                        continue;
                    }
                }
                if ($byte === "\r") {
                    $pendingCr = true;
                } elseif ($byte === "\n") {
                    ++$line;
                    $lineStart = $start + $index + 1;
                }
            }
        }
        if (
            $pendingCr && ($this->byteOffset === $this->source->byteLength()
                || $this->source->sliceBytes($this->byteOffset, $this->byteOffset + 1) !== "\n")
        ) {
            ++$line;
            $lineStart = $this->byteOffset;
        }

        return [$line, $lineStart];
    }

    private function findLineEnd(): int
    {
        $length = $this->source->byteLength();
        for ($start = $this->byteOffset; $start < $length; $start += 8192) {
            $chunk = $this->source->sliceBytes($start, min($start + 8192, $length));
            $end = strcspn($chunk, "\r\n");
            if ($end < strlen($chunk)) {
                return $start + $end;
            }
        }

        return $length;
    }
}
