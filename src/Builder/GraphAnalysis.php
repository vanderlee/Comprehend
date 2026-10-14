<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use LogicException;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Stub;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;

/** Conservative structural analysis: opaque custom recognizers remain runtime guarded. */
final class GraphAnalysis
{
    private array $nodes = [];
    private array $paths = [];
    private array $nullable = [];

    public function validate(Parser $root): array
    {
        $this->collect($root, []);
        $warnings = [];
        foreach ($this->nodes as $id => $node) {
            $known = $this->isKnown($node);
            $this->nullable[$id] = $known ? false : null;
            if (!$known) { $warnings[] = 'Opaque parser: ' . implode(' -> ', $this->paths[$id]); }
        }
        do {
            $changed = false;
            foreach ($this->nodes as $id => $node) {
                $nullable = $this->canMatchEmpty($node);
                if ($nullable !== $this->nullable[$id]) { $this->nullable[$id] = $nullable; $changed = true; }
            }
        } while ($changed);
        foreach ($this->nodes as $id => $node) {
            if ($node::class === Repeat::class && GraphState::value($node, 'max') !== 0) {
                $child = GraphState::value($node, 'parser');
                if ($this->nullable[spl_object_id($child)] === true) {
                    throw new LogicException('Nullable repetition: ' . implode(' -> ', $this->paths[$id]) . ' -> ' . $child->graphLabel());
                }
            }
        }
        $completed = [];
        foreach ($this->nodes as $node) { $this->checkLeadingCycles($node, [], $completed); }
        return $warnings;
    }

    private function collect(Parser $node, array $path): void
    {
        $id = spl_object_id($node);
        if (isset($this->nodes[$id])) { return; }
        $this->nodes[$id] = $node;
        $this->paths[$id] = [...$path, $node->graphLabel()];
        if ($node instanceof Stub && $node->getParser() === null) {
            throw new LogicException('Unresolved stub: ' . implode(' -> ', $this->paths[$id]));
        }
        foreach (GraphState::children($node) as $child) { $this->collect($child, $this->paths[$id]); }
    }

    private function isKnown(Parser $node): bool
    {
        if ($node instanceof AbstractRuleset || $node instanceof RuleReference || $node instanceof Implementation) { return true; }
        $class = $node::class;
        return in_array($class, [Stub::class, Sequence::class, Choice::class, Repeat::class, All::class, Except::class, Not::class], true)
            || in_array($class, [\Vanderlee\Comprehend\Directive\CaseSensitive::class,
                \Vanderlee\Comprehend\Directive\Prefer::class, \Vanderlee\Comprehend\Directive\Space::class,
                \Vanderlee\Comprehend\Directive\Lexeme::class, \Vanderlee\Comprehend\Directive\Trivia::class], true)
            || in_array($class, [\Vanderlee\Comprehend\Parser\Terminal\Any::class, \Vanderlee\Comprehend\Parser\Terminal\Char::class,
                \Vanderlee\Comprehend\Parser\Terminal\Text::class, \Vanderlee\Comprehend\Parser\Terminal\Range::class,
                \Vanderlee\Comprehend\Parser\Terminal\Set::class, \Vanderlee\Comprehend\Parser\Terminal\Regex::class,
                \Vanderlee\Comprehend\Parser\Terminal\Integer::class, \Vanderlee\Comprehend\Parser\Terminal\Nothing::class,
                \Vanderlee\Comprehend\Parser\Terminal\Start::class, \Vanderlee\Comprehend\Parser\Terminal\End::class], true);
    }

    private function grammarChildren(Parser $node): array
    {
        if ($node instanceof RuleReference || $node instanceof Implementation) { return [$node->graphTarget()]; }
        if ($node instanceof AbstractRuleset) { return [$node->resolveRule(AbstractRuleset::ROOT)]; }
        if ($node instanceof Sequence || $node instanceof Choice) { return array_values(iterator_to_array($node)); }
        if ($node instanceof All) { return GraphState::value($node, 'parsers'); }
        if ($node instanceof Except) {
            return [GraphState::value($node, 'parserMatch'), GraphState::value($node, 'parserNot')];
        }
        $child = GraphState::value($node, 'parser');
        return $child instanceof Parser ? [$child] : [];
    }

    private function canMatchEmpty(Parser $node): ?bool
    {
        if (!$this->isKnown($node)) { return null; }
        if ($node instanceof Not || $node instanceof \Vanderlee\Comprehend\Parser\Terminal\Nothing
            || $node instanceof \Vanderlee\Comprehend\Parser\Terminal\Start || $node instanceof \Vanderlee\Comprehend\Parser\Terminal\End) { return true; }
        $children = $this->grammarChildren($node);
        if ($node instanceof Repeat && GraphState::value($node, 'min') === 0) { return true; }
        if ($node instanceof Except) { return $this->nullable[spl_object_id(GraphState::value($node, 'parserMatch'))]; }
        if ($children === []) { return false; }
        $values = array_map(fn ($child) => $this->nullable[spl_object_id($child)], $children);
        if ($node instanceof Choice || $node instanceof All) {
            return in_array(true, $values, true) ? true : (in_array(null, $values, true) ? null : false);
        }
        return in_array(false, $values, true) ? false : (in_array(null, $values, true) ? null : true);
    }

    private function leadingChildren(Parser $node): array
    {
        if (!$this->isKnown($node)) { return []; }
        if ($node instanceof Repeat && GraphState::value($node, 'max') === 0) { return []; }
        $children = $this->grammarChildren($node);
        if (!$node instanceof Sequence) { return $children; }
        $leading = [];
        foreach ($children as $child) {
            $leading[] = $child;
            if ($this->nullable[spl_object_id($child)] !== true) { break; }
        }
        return $leading;
    }

    private function checkLeadingCycles(Parser $node, array $active, array &$completed): void
    {
        $id = spl_object_id($node);
        if (isset($active[$id])) {
            throw new LogicException('Left-recursive grammar: ' . implode(' -> ', [...array_values($active), $node->graphLabel()]));
        }
        if (isset($completed[$id])) { return; }
        $active[$id] = $node->graphLabel();
        foreach ($this->leadingChildren($node) as $child) { $this->checkLeadingCycles($child, $active, $completed); }
        $completed[$id] = true;
    }
}
