<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use InvalidArgumentException;

/** Independent preparation options; graph isolation, resolution and safety checks always run. */
final class FreezeFlags
{
    public const NONE = 0;
    public const OPTIMIZE = 1 << 0;
    public const DEFAULT = self::OPTIMIZE;

    private function __construct()
    {
        // Flags are constants, not instances.
    }

    /** @internal Reject unsupported bits before preparing a graph or consulting its cache. */
    public static function validate(int $flags): void
    {
        if (($flags & ~self::OPTIMIZE) !== 0) {
            throw new InvalidArgumentException('Unknown freeze flags: ' . $flags);
        }
    }
}
