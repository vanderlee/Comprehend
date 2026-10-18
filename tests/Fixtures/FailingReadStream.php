<?php

declare(strict_types=1);

namespace Tests\Fixtures;

// PHP's stream wrapper protocol requires these exact method names.
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

/** Seekable borrowed stream that fails reads without reaching EOF. */
final class FailingReadStream
{
    /** @var resource|null */
    public $context;
    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string|false
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $this->position = $whence === SEEK_CUR ? $this->position + $offset : $offset;

        return true;
    }

    /** @return array<string, int> */
    public function stream_stat(): array
    {
        return [];
    }
}
