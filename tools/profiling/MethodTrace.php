<?php

declare(strict_types=1);

namespace ComprehendProfiling;

/** Development-only instrumentation; never participates in the production parser graph. */
final class MethodTrace
{
    private static bool $enabled = false;
    private static array $stack = [];
    private static array $totals = [];
    private static array $allocations = [];

    public static function start(): void
    {
        self::$stack = [];
        self::$totals = [];
        self::$allocations = [];
        self::$enabled = true;
    }

    public static function enter(string $method): void
    {
        if (self::$enabled) {
            self::$stack[] = [$method, hrtime(true), 0];
        }
    }

    public static function leave(): void
    {
        if (!self::$enabled) {
            return;
        }
        [$method, $start, $children] = array_pop(self::$stack);
        $elapsed = hrtime(true) - $start;
        $row = self::$totals[$method] ?? ['calls' => 0, 'inclusive_ns' => 0, 'self_ns' => 0];
        ++$row['calls'];
        $row['inclusive_ns'] += $elapsed;
        $row['self_ns'] += $elapsed - $children;
        self::$totals[$method] = $row;
        if (self::$stack !== []) {
            self::$stack[array_key_last(self::$stack)][2] += $elapsed;
        }
    }

    public static function allocated(object $object): object
    {
        if (self::$enabled) {
            self::$allocations[$object::class] = (self::$allocations[$object::class] ?? 0) + 1;
        }

        return $object;
    }

    public static function stop(): array
    {
        self::$enabled = false;
        $rows = self::$totals;
        uasort($rows, static fn(array $a, array $b): int => $b['self_ns'] <=> $a['self_ns']);

        arsort(self::$allocations);

        return ['methods' => $rows, 'allocations' => self::$allocations];
    }
}
