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
    /** @var array<int, list<string>> */
    private array $paths = [];
    /** @var array<int, bool|null> */
    private array $nullable = [];

    /** @return list<string> */
    public function validate(Parser $root): array
    {
        $this->nodes = [];
        $this->paths = [];
        $this->nullable = [];
        $this->collect($root, []);
        $warnings = $this->initializeNullability();
        $this->resolveNullability();
        $lookup = new NullabilityLookup($this->nullable);
        $this->rejectNullableProgress($lookup);

        $completed = [];
        foreach ($this->nodes as $node) {
            $this->checkLeadingCycles($node, $lookup, [], $completed);
        }

        return $warnings;
    }

    /** @param list<string> $path */
    private function collect(Parser $node, array $path): void
    {
        $id = spl_object_id($node);
        if (isset($this->nodes[$id])) {
            return;
        }

        $this->nodes[$id] = $node;
        $this->paths[$id] = [...$path, $node->graphLabel()];
        if ($node instanceof AnalyzableParser) {
            $error = $node->analysisError();
            if ($error !== null) {
                throw new LogicException($error . ': ' . implode(' -> ', $this->paths[$id]));
            }
        }
        foreach ($node->children() as $child) {
            $this->collect($child, $this->paths[$id]);
        }
    }

    /** @return list<string> */
    private function initializeNullability(): array
    {
        $warnings = [];
        foreach ($this->nodes as $id => $node) {
            $known = $node instanceof AnalyzableParser;
            $this->nullable[$id] = $known ? false : null;
            if (!$known) {
                $warnings[] = 'Opaque parser: ' . implode(' -> ', $this->paths[$id]);
            }
        }

        return $warnings;
    }

    private function resolveNullability(): void
    {
        // Read one stable set of estimates per iteration. Monotone estimates over
        // false < unknown < true can increase at most twice per node.
        do {
            $lookup = new NullabilityLookup($this->nullable);
            $changed = $this->updateNullability($lookup);
        } while ($changed);
    }

    /** Return whether this pass changed any estimate. */
    private function updateNullability(NullabilityLookup $lookup): bool
    {
        $changed = false;
        foreach ($this->nodes as $id => $node) {
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
                throw new LogicException('Non-monotone nullability: ' . implode(' -> ', $this->paths[$id]));
            }
            $this->nullable[$id] = $nullable;
            $changed = true;
        }

        return $changed;
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
                        'Nullable repetition: ' . implode(' -> ', $this->paths[$id]) . ' -> ' . $child->graphLabel(),
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
        array $active,
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
        if ($node instanceof AnalyzableParser) {
            foreach ($node->analysisLeadingParsers($lookup) as $child) {
                $lookup->get($child);
                $this->checkLeadingCycles($child, $lookup, $active, $completed);
            }
        }

        $completed[$id] = true;
    }
}
