<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use LogicException;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Lexeme;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Integer;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Start;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/** Conservative structural analysis: opaque custom recognizers remain runtime guarded. */
final class GraphAnalysis
{
    /**
     * @var array<int, Parser>
     */
    private array $nodes = [];
    /**
     * @var array<int, list<string>>
     */
    private array $paths = [];
    /**
     * @var array<int, bool|null>
     */
    private array $nullable = [];

    /**
     * @return list<string>
     */
    public function validate(Parser $root): array
    {
        $this->collect($root, []);
        $warnings = $this->initializeNullability();
        $this->resolveNullability();
        $this->rejectNullableRepetition();

        $completed = [];
        foreach ($this->nodes as $node) {
            $this->checkLeadingCycles($node, [], $completed);
        }

        return $warnings;
    }

    /**
     * @param list<string> $path
     */
    private function collect(Parser $node, array $path): void
    {
        $id = spl_object_id($node);
        if (isset($this->nodes[$id])) {
            return;
        }

        $this->nodes[$id] = $node;
        $this->paths[$id] = [...$path, $node->graphLabel()];
        if (
            $node instanceof Stub
            && $node->getParser() === null
        ) {
            throw new LogicException('Unresolved stub: ' . implode(' -> ', $this->paths[$id]));
        }
        foreach (GraphState::children($node) as $child) {
            $this->collect($child, $this->paths[$id]);
        }
    }

    /**
     * @return list<string>
     */
    private function initializeNullability(): array
    {
        $warnings = [];
        foreach ($this->nodes as $id => $node) {
            $known = $this->isKnown($node);
            $this->nullable[$id] = $known ? false : null;
            if (!$known) {
                $warnings[] = 'Opaque parser: ' . implode(' -> ', $this->paths[$id]);
            }
        }

        return $warnings;
    }

    private function isKnown(Parser $node): bool
    {
        if (
            $node instanceof AbstractRuleset
            || $node instanceof RuleReference
            || $node instanceof Implementation
        ) {
            return true;
        }

        return in_array($node::class, [
            Stub::class,
            Sequence::class,
            Choice::class,
            Repeat::class,
            All::class,
            Except::class,
            Not::class,
            CaseSensitive::class,
            Prefer::class,
            Space::class,
            Lexeme::class,
            Trivia::class,
            Any::class,
            Char::class,
            Text::class,
            Range::class,
            Set::class,
            Regex::class,
            Integer::class,
            Nothing::class,
            Start::class,
            End::class,
        ], true);
    }

    private function resolveNullability(): void
    {
        // Recursive rules require a fixed point rather than one traversal.
        do {
            $changed = false;
            foreach ($this->nodes as $id => $node) {
                $nullable = $this->canMatchEmpty($node);
                if ($nullable !== $this->nullable[$id]) {
                    $this->nullable[$id] = $nullable;
                    $changed = true;
                }
            }
        } while ($changed);
    }

    private function canMatchEmpty(Parser $node): ?bool
    {
        if (!$this->isKnown($node)) {
            return null;
        }
        if (
            $node instanceof Not
            || $node instanceof Nothing
            || $node instanceof Start
            || $node instanceof End
        ) {
            return true;
        }

        $children = $this->grammarChildren($node);
        if ($node instanceof Repeat && $node->getMinimum() === 0) {
            return true;
        }
        if ($node instanceof Except) {
            return $this->nullable[spl_object_id($node->getMatchParser())];
        }
        if ($children === []) {
            return false;
        }

        $values = array_map(fn($child) => $this->nullable[spl_object_id($child)], $children);
        if (
            $node instanceof Choice
            || $node instanceof All
        ) {
            if (in_array(true, $values, true)) {
                return true;
            }

            return in_array(null, $values, true) ? null : false;
        }
        if (in_array(false, $values, true)) {
            return false;
        }

        return in_array(null, $values, true) ? null : true;
    }

    /**
     * @return list<Parser>
     */
    private function grammarChildren(Parser $node): array
    {
        if ($node instanceof RuleReference || $node instanceof Implementation) {
            return [$node->graphTarget()];
        }
        if ($node instanceof AbstractRuleset) {
            return [$node->resolveRule(AbstractRuleset::ROOT)];
        }
        if (
            $node instanceof Sequence
            || $node instanceof Choice
        ) {
            return array_values(iterator_to_array($node));
        }
        if ($node instanceof All) {
            return array_values($node->getParsers());
        }
        if ($node instanceof Except) {
            return [$node->getMatchParser(), $node->getExcludedParser()];
        }

        if (
            $node instanceof Repeat
            || $node instanceof Not
            || $node instanceof Stub
            || $node instanceof CaseSensitive
            || $node instanceof Prefer
            || $node instanceof Space
            || $node instanceof Lexeme
            || $node instanceof Trivia
        ) {
            $child = $node->getParser();
            return $child === null ? [] : [$child];
        }

        return [];
    }

    private function rejectNullableRepetition(): void
    {
        foreach ($this->nodes as $id => $node) {
            if (
                $node::class !== Repeat::class
                || $node->getMaximum() === 0
            ) {
                continue;
            }

            $child = $node->getParser();
            if ($this->nullable[spl_object_id($child)] === true) {
                throw new LogicException(
                    'Nullable repetition: ' . implode(' -> ', $this->paths[$id]) . ' -> ' . $child->graphLabel(),
                );
            }
        }
    }

    /**
     * @param array<int, string> $active
     * @param array<int, true> $completed
     */
    private function checkLeadingCycles(Parser $node, array $active, array &$completed): void
    {
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
        foreach ($this->leadingChildren($node) as $child) {
            $this->checkLeadingCycles($child, $active, $completed);
        }

        $completed[$id] = true;
    }

    /**
     * @return list<Parser>
     */
    private function leadingChildren(Parser $node): array
    {
        if (!$this->isKnown($node)) {
            return [];
        }
        if ($node instanceof Repeat && $node->getMaximum() === 0) {
            return [];
        }

        $children = $this->grammarChildren($node);
        if (!$node instanceof Sequence) {
            return $children;
        }

        $leading = [];
        foreach ($children as $child) {
            $leading[] = $child;
            if ($this->nullable[spl_object_id($child)] !== true) {
                break;
            }
        }

        return $leading;
    }
}
