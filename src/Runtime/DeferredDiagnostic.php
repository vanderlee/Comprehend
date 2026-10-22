<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Source\Source;

/**
 * @internal Immutable ordered union of tied observations, shared by diagnostic checkpoints.
 * Observations never change. Materialization caches output and releases operand references.
 */
final class DeferredDiagnostic
{
    // Compact long probe histories even when callers never request their diagnostic.
    private const MAX_DEPTH = 128;

    public readonly int $byteOffset;
    private readonly int $depth;
    private ?Diagnostic $cache = null;
    private Diagnostic|self|null $left;
    private Diagnostic|self|null $right;

    private function __construct(
        private readonly Source $source,
        Diagnostic|self $left,
        Diagnostic|self $right,
    ) {
        $this->left = $left;
        $this->right = $right;
        $this->byteOffset = $left->byteOffset;
        $this->depth = 1 + max(
            $left instanceof self && $left->cache === null ? $left->depth : 0,
            $right instanceof self && $right->cache === null ? $right->depth : 0,
        );
    }

    public static function merge(
        Source $source,
        Diagnostic|self|null $left,
        Diagnostic|self|null $right,
    ): Diagnostic|self|null {
        if ($left === null || ($right !== null && $right->byteOffset > $left->byteOffset)) {
            return $right;
        }
        if ($right === null || $left->byteOffset > $right->byteOffset) {
            return $left;
        }
        if ($left === $right && $left instanceof self) {
            return $left;
        }

        $leftSnapshot = $left instanceof self ? $left->cache : $left;
        $rightSnapshot = $right instanceof self ? $right->cache : $right;
        if (
            $leftSnapshot !== null && $rightSnapshot !== null
            && $leftSnapshot->expected === $rightSnapshot->expected
            && $leftSnapshot->rulePaths === $rightSnapshot->rulePaths
        ) {
            if (
                $left instanceof self
                || (count($leftSnapshot->expected) <= 1 && count($leftSnapshot->rulePaths) <= 1)
            ) {
                return $leftSnapshot;
            }

            return self::mergeMaterialized($source, $leftSnapshot, $rightSnapshot);
        }

        // SORT_REGULAR compares numeric strings numerically, and other strings lexically.
        // Preserve the original pairwise operations when those comparisons can interact.
        if (self::hasNumericPath($left) || self::hasNumericPath($right)) {
            return self::mergeMaterialized($source, $left, $right);
        }

        $union = new self($source, $left, $right);

        return $union->depth > self::MAX_DEPTH ? self::resolve($union) : $union;
    }

    /** @return ($failure is null ? null : Diagnostic) */
    public static function resolve(Diagnostic|self|null $failure): ?Diagnostic
    {
        return $failure instanceof self ? $failure->materialize() : $failure;
    }

    private static function hasNumericPath(Diagnostic|self $failure): bool
    {
        if ($failure instanceof self) {
            return false;
        }
        foreach ($failure->rulePaths as $path) {
            foreach ($path as $label) {
                if (is_numeric($label)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function materialize(): Diagnostic
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $expectationChunks = [];
        $pathChunks = [];
        $seen = [];
        $pending = [$this];
        while ($pending !== []) {
            $entry = array_pop($pending);
            $id = spl_object_id($entry);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if ($entry instanceof self) {
                if ($entry->cache === null) {
                    // A shared subtree's first visit determines insertion order.
                    if ($entry->right !== null) {
                        $pending[] = $entry->right;
                    }
                    if ($entry->left !== null) {
                        $pending[] = $entry->left;
                    }
                    continue;
                }
                $entry = $entry->cache;
            }
            $expectationChunks[] = $entry->expected;
            $pathChunks[] = $entry->rulePaths;
        }
        $expected = array_values(array_unique(array_merge(...$expectationChunks)));
        $paths = array_values(array_unique(array_merge(...$pathChunks), SORT_REGULAR));

        // Reuse already observed operands without forcing unobserved intermediate snapshots.
        $left = $this->left instanceof self ? $this->left->cache : $this->left;
        $right = $this->right instanceof self ? $this->right->cache : $this->right;
        if ($left !== null && $expected === $left->expected && $paths === $left->rulePaths) {
            return $this->remember($left);
        }
        if ($right !== null && $expected === $right->expected && $paths === $right->rulePaths) {
            return $this->remember($right);
        }

        return $this->remember(new Diagnostic($this->source, $this->byteOffset, $expected, $paths));
    }

    private function remember(Diagnostic $snapshot): Diagnostic
    {
        $this->cache = $snapshot;
        $this->left = null;
        $this->right = null;

        return $snapshot;
    }

    /** @return ($left is null ? ($right is null ? null : Diagnostic) : Diagnostic) */
    public static function mergeMaterialized(
        Source $source,
        Diagnostic|self|null $left,
        Diagnostic|self|null $right,
    ): ?Diagnostic {
        if ($left === null || ($right !== null && $right->byteOffset > $left->byteOffset)) {
            return $right instanceof self ? $right->materialize() : $right;
        }
        if ($right === null || $left->byteOffset > $right->byteOffset) {
            return $left instanceof self ? $left->materialize() : $left;
        }
        $left = $left instanceof self ? $left->materialize() : $left;
        $right = $right instanceof self ? $right->materialize() : $right;
        $expected = array_values(array_unique(array_merge($left->expected, $right->expected)));
        $paths = array_values(array_unique(array_merge($left->rulePaths, $right->rulePaths), SORT_REGULAR));

        if ($expected === $left->expected && $paths === $left->rulePaths) {
            return $left;
        }
        if ($expected === $right->expected && $paths === $right->rulePaths) {
            return $right;
        }

        return new Diagnostic($source, $left->byteOffset, $expected, $paths);
    }
}
