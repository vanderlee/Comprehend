<?php

declare(strict_types=1);

namespace ComprehendProfiling;

use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Result\Diagnostic;

/** Development-only instrumentation; never participates in the production parser graph. */
final class MethodTrace
{
    private static bool $enabled = false;
    private static array $stack = [];
    private static array $totals = [];
    private static array $allocations = [];
    private static bool $failureDetails = false;
    private static array $failureAllocations = [];

    public static function start(bool $failureDetails = false): void
    {
        self::$stack = [];
        self::$totals = [];
        self::$allocations = [];
        self::$failureDetails = $failureDetails;
        self::$failureAllocations = [];
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
            if (self::$failureDetails && ($object instanceof Failure || $object instanceof Diagnostic)) {
                $details = [
                    'class' => $object::class,
                    'callers' => array_column(self::$stack, 0),
                ];
                if ($object instanceof Diagnostic) {
                    $details['byte_offset'] = $object->byteOffset;
                    $details['expected'] = $object->expected;
                    $details['rule_paths'] = $object->rulePaths;
                } else {
                    $details['progress_bytes'] = $object->length;
                }
                $key = json_encode($details, JSON_THROW_ON_ERROR);
                self::$failureAllocations[$key] ??= $details + ['count' => 0];
                ++self::$failureAllocations[$key]['count'];
            }
        }

        return $object;
    }

    public static function stop(): array
    {
        self::$enabled = false;
        $rows = self::$totals;
        uasort($rows, static fn(array $a, array $b): int => $b['self_ns'] <=> $a['self_ns']);

        arsort(self::$allocations);

        return [
            'methods' => $rows, 'allocations' => self::$allocations,
            'failure_allocations' => array_values(self::$failureAllocations),
        ];
    }
}
