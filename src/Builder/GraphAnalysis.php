<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use LogicException;
use Vanderlee\Comprehend\Parser\Parser;

/** Checks optional structural contracts; opaque custom parsers retain runtime guards. */
final class GraphAnalysis
{
    /** @var array<int, Parser> */
    private array $nodes = [];
    /** @var array<int, int|null> First-discovery parents preserve diagnostic paths through shared graphs. */
    private array $parents = [];
    /** @var array<int, string> Labels captured during collection, before custom analysis runs. */
    private array $labels = [];
    /** @var array<int, bool|null> */
    private array $nullable = [];

    /** @return list<string> */
    public function validate(Parser $root): array
    {
        $this->nodes = [];
        $this->parents = [];
        $this->labels = [];
        $this->nullable = [];
        $this->collect($root);
        $warnings = $this->initializeNullability();
        $this->resolveNullability();
        $lookup = new NullabilityLookup($this->nullable);
        $this->rejectNullableProgress($lookup);

        $completed = [];
        $active = [];
        foreach ($this->nodes as $node) {
            $this->checkLeadingCycles($node, $lookup, $active, $completed);
        }

        return $warnings;
    }

    private function collect(Parser $node, ?int $parent = null): void
    {
        $id = spl_object_id($node);
        if (isset($this->nodes[$id])) {
            return;
        }

        $this->nodes[$id] = $node;
        $this->parents[$id] = $parent;
        $this->labels[$id] = $node->graphLabel();
        if ($node instanceof AnalyzableParser) {
            $error = $node->analysisError();
            if ($error !== null) {
                throw new LogicException($error . ': ' . $this->path($id));
            }
        }
        foreach ($node->children() as $child) {
            $this->collect($child, $id);
        }
    }

    /** Materialize a first-discovery path only when a warning or error needs it. */
    private function path(int $id): string
    {
        $labels = [];
        do {
            $labels[] = $this->labels[$id];
            $id = $this->parents[$id];
        } while ($id !== null);

        return implode(' -> ', array_reverse($labels));
    }

    /** @return list<string> */
    private function initializeNullability(): array
    {
        $warnings = [];
        foreach ($this->nodes as $id => $node) {
            $known = $node instanceof AnalyzableParser;
            $this->nullable[$id] = $known ? false : null;
            if (!$known) {
                $warnings[] = 'Opaque parser: ' . $this->path($id);
            }
        }

        return $warnings;
    }

    private function resolveNullability(): void
    {
        $pending = $this->nodes;
        $dependents = [];
        do {
            $changes = $this->updateNullability($pending, $dependents);
            $pending = [];
            // The round's lookup has been released, so publishing these estimates
            // preserves stable reads without copying the entire map on every round.
            foreach ($changes as $id => $nullable) {
                $this->nullable[$id] = $nullable;
                foreach ($dependents[$id] ?? [] as $dependent => $_) {
                    $pending[$dependent] = $this->nodes[$dependent];
                }
            }
        } while ($pending !== []);
    }

    /**
     * @param array<int, Parser> $pending
     * @param array<int, array<int, true>> $dependents
     * @return array<int, bool|null> Changed estimates, published only after this round.
     */
    private function updateNullability(array $pending, array &$dependents): array
    {
        $changes = [];
        $id = 0;
        // Actual reads capture self-references and discover operands as short circuits open.
        $lookup = new NullabilityLookup(
            $this->nullable,
            static function (int $dependency) use (&$dependents, &$id): void {
                $dependents[$dependency][$id] = true;
            },
        );
        foreach ($pending as $id => $node) {
            if (!$node instanceof AnalyzableParser) {
                continue;
            }
            $nullable = $node->analysisNullability($lookup);
            $previous = $this->nullable[$id];
            if ($nullable === $previous) {
                continue;
            }
            if (
                $previous === true
                || (
                    $previous === null
                    && $nullable === false
                )
            ) {
                throw new LogicException('Non-monotone nullability: ' . $this->path($id));
            }
            $changes[$id] = $nullable;
        }

        return $changes;
    }

    private function rejectNullableProgress(NullabilityLookup $lookup): void
    {
        foreach ($this->nodes as $id => $node) {
            if (!$node instanceof AnalyzableParser) {
                continue;
            }
            foreach ($node->analysisProgressParsers() as $child) {
                if ($lookup->get($child) === true) {
                    throw new LogicException(
                        'Nullable repetition: ' . $this->path($id) . ' -> ' . $child->graphLabel(),
                    );
                }
            }
        }
    }

    /**
     * @param array<int, string> $active
     * @param array<int, true> $completed
     */
    private function checkLeadingCycles(
        Parser $node,
        NullabilityLookup $lookup,
        array &$active,
        array &$completed,
    ): void {
        $id = spl_object_id($node);
        if (isset($active[$id])) {
            throw new LogicException(
                'Left-recursive grammar: ' . implode(' -> ', [...array_values($active), $node->graphLabel()]),
            );
        }
        if (isset($completed[$id])) {
            return;
        }

        $active[$id] = $node->graphLabel();
        try {
            if ($node instanceof AnalyzableParser) {
                foreach ($node->analysisLeadingParsers($lookup) as $child) {
                    $lookup->get($child);
                    $this->checkLeadingCycles($child, $lookup, $active, $completed);
                }
            }

            $completed[$id] = true;
        } finally {
            unset($active[$id]);
        }
    }
}
