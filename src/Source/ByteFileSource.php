<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Provides arbitrary source bytes from an owned stable disk snapshot. Uses a bounded read page
 * so file-backed recognition need not retain the full input in memory.
 */
class ByteFileSource implements Source
{
    /** @var resource|null */
    private $handle;
    /** @var int<4, max> */
    private readonly int $pageSize;
    private int $length = 0;
    private int $cacheStart = -1;
    private string $cache = '';

    /** A path is opened/closed here; a borrowed seekable stream keeps its position and ownership. */
    public function __construct(mixed $input, int $pageSize = 8192)
    {
        if ($pageSize < 4) {
            throw new InvalidArgumentException('File page size must be at least 4 bytes');
        }
        $this->pageSize = $pageSize;

        $owned = is_string($input);
        $stream = $owned ? @fopen($input, 'rb') : $input;
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('File source requires a readable path or seekable stream');
        }

        $position = ftell($stream);
        $snapshot = null;
        try {
            $metadata = stream_get_meta_data($stream);
            if (
                !$metadata['seekable']
                || strpbrk($metadata['mode'], 'r+') === false
                || $position === false
                || fseek($stream, 0) !== 0
            ) {
                throw new InvalidArgumentException('File source requires a seekable stream');
            }

            $snapshot = tmpfile();
            if ($snapshot === false) {
                throw new RuntimeException('Cannot create file source snapshot');
            }
            $this->copyToSnapshot($stream, $snapshot);

            $this->handle = $snapshot;
            $this->validateContent();
        } catch (Throwable $error) {
            if (is_resource($snapshot)) {
                fclose($snapshot);
            }
            throw $error;
        } finally {
            if ($owned) {
                fclose($stream);
            } elseif ($position !== false) {
                fseek($stream, $position);
            }
        }
    }

    /**
     * @param resource $stream
     * @param resource $snapshot
     */
    private function copyToSnapshot($stream, $snapshot): void
    {
        while (!feof($stream)) {
            $chunk = fread($stream, $this->pageSize);
            if ($chunk === false || ($chunk === '' && !feof($stream))) {
                throw new RuntimeException('Cannot read file source');
            }

            $written = 0;
            while ($written < strlen($chunk)) {
                $count = fwrite($snapshot, substr($chunk, $written));
                if ($count === false || $count === 0) {
                    throw new RuntimeException('Cannot write file source snapshot');
                }
                $written += $count;
            }

            $this->length += strlen($chunk);
        }
    }

    protected function validateContent(): void {}

    public function mode(): SourceMode
    {
        return SourceMode::BYTES;
    }

    public function byteLength(): int
    {
        return $this->length;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->cache = '';
        $this->cacheStart = -1;
    }

    public function sliceBytes(int $start, int $end): string
    {
        $this->assertReadableOffset($start);
        $this->assertReadableOffset($end);
        if ($end < $start) {
            throw new InvalidArgumentException('Span end must not precede its start');
        }

        $output = '';
        while ($start < $end) {
            $page = intdiv($start, $this->pageSize) * $this->pageSize;
            if ($page !== $this->cacheStart) {
                $this->loadPage($page);
            }

            $count = min($end - $start, strlen($this->cache) - ($start - $page));
            $output .= substr($this->cache, $start - $page, $count);
            $start += $count;
        }

        return $output;
    }

    protected function assertReadableOffset(int $offset): void
    {
        if ($this->isClosed()) {
            throw new LogicException('File source is closed');
        }
        if ($offset < 0 || $offset > $this->length) {
            throw new InvalidArgumentException('Byte offset is outside the source');
        }
    }

    /** Read a checked octet without constructing a slice; null denotes EOF. */
    protected function readByteValue(int $byteOffset): ?int
    {
        $this->assertReadableOffset($byteOffset);
        if ($byteOffset === $this->length) {
            return null;
        }
        $page = intdiv($byteOffset, $this->pageSize) * $this->pageSize;
        if ($page !== $this->cacheStart) {
            $this->loadPage($page);
        }

        return ord($this->cache[$byteOffset - $page]);
    }

    public function isClosed(): bool
    {
        return !is_resource($this->handle);
    }

    private function loadPage(int $pageStart): void
    {
        $handle = $this->handle;
        if (!is_resource($handle)) {
            throw new LogicException('File source is closed');
        }
        if (fseek($handle, $pageStart) !== 0) {
            throw new RuntimeException('Cannot seek file source');
        }

        $expected = min($this->pageSize, $this->length - $pageStart);
        $bytes = '';
        $remaining = $expected;
        while ($remaining > 0) {
            $part = fread($handle, $remaining);
            if ($part === false || $part === '') {
                throw new RuntimeException('Cannot read file source snapshot');
            }
            $bytes .= $part;
            $remaining -= strlen($part);
        }

        // Publish a cache page only after the complete read succeeds.
        $this->cache = $bytes;
        $this->cacheStart = $pageStart;
    }

    public function isUnitBoundary(int $byteOffset): bool
    {
        $this->assertReadableOffset($byteOffset);

        return true;
    }

    public function readUnit(int $byteOffset): ?Span
    {
        $this->assertReadableOffset($byteOffset);

        return $byteOffset === $this->length ? null : $this->span($byteOffset, $byteOffset + 1);
    }

    public function span(int $start, int $end): Span
    {
        return new Span($this, $start, $end);
    }

    private function __clone() {}
}
