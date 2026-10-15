<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use InvalidArgumentException;
use JsonSerializable;
use Vanderlee\Comprehend\Source\Span;

/**
 * Represents named syntax projected from ConcreteNode with source spans and surrounding gaps.
 * Offers structural navigation and raw text independently of semantic values.
 */
final class AstNode implements JsonSerializable
{
    use Serialization;

    /**
     * @param list<AstNode> $children A null name denotes the synthetic root.
     * @param list<string> $tags
     * @param list<Span> $preamble
     * @param list<Span> $postamble
     */
    public function __construct(
        public readonly string|int|null $name,
        public readonly ?string $group,
        public readonly Span $span,
        public readonly array $children = [],
        public readonly array $tags = [],
        public readonly array $preamble = [],
        public readonly array $postamble = [],
    ) {
        self::validateStructure($span, $children, $tags, $preamble, $postamble);
    }

    /**
     * @param array<array-key, mixed> $children
     * @param array<array-key, mixed> $tags
     * @param array<array-key, mixed> $preamble
     * @param array<array-key, mixed> $postamble
     */
    private static function validateStructure(
        Span $span,
        array $children,
        array $tags,
        array $preamble,
        array $postamble,
    ): void {
        if (!array_is_list($tags) || !array_is_list($preamble) || !array_is_list($postamble)) {
            throw new InvalidArgumentException('Tags and surrounding spans must be lists');
        }
        foreach ($tags as $tag) {
            if (!is_string($tag) || $tag === '') {
                throw new InvalidArgumentException('Node tags must be nonempty strings');
            }
        }
        foreach ([...$preamble, ...$postamble] as $fragment) {
            if (!$fragment instanceof Span || $fragment->source !== $span->source) {
                throw new InvalidArgumentException('Surrounding spans must share the node source');
            }
        }
        if (!array_is_list($children)) {
            throw new InvalidArgumentException('AST children must be a list');
        }
        foreach ($children as $child) {
            if (!$child instanceof self || $child->span->source !== $span->source) {
                throw new InvalidArgumentException('AST children must share their parent source');
            }
        }
    }

    public static function fromConcrete(ConcreteNode $root): self
    {
        $nodes = self::project($root);

        if (!$root->isTrivia && $root->name !== null) {
            return $nodes[0];
        }

        return new self(null, null, $root->span, self::surround($nodes, $root->span), $root->tags);
    }

    /**
     * @return list<AstNode>
     * @param list<string> $inheritedTags
     */
    private static function project(ConcreteNode $node, array $inheritedTags = []): array
    {
        if ($node->isTrivia) {
            return [];
        }
        $children = [];
        $tags = array_values(array_unique([...$inheritedTags, ...$node->tags]));
        if (!$node->semanticLeaf) {
            foreach ($node->children as $child) {
                array_push($children, ...self::project($child, $node->name === null ? $tags : []));
            }
        }

        return $node->name === null ? $children
            : [new self($node->name, $node->group, $node->span, self::surround($children, $node->span), $tags)];
    }

    /**
     * Unnamed gaps lead the next sibling; the final gap trails the last sibling.
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private static function surround(array $nodes, Span $parent): array
    {
        $cursor = $parent->start;
        $last = count($nodes) - 1;
        foreach ($nodes as $index => $node) {
            $preamble = $node->span->start > $cursor && $node->span->start <= $parent->end
                ? [$parent->source->span($cursor, $node->span->start)] : [];
            $cursor = max($cursor, $node->span->end);
            $postamble = $index === $last && $cursor < $parent->end
                ? [$parent->source->span($cursor, $parent->end)] : [];
            $nodes[$index] = new self(
                $node->name,
                $node->group,
                $node->span,
                $node->children,
                $node->tags,
                $preamble,
                $postamble,
            );
        }

        return $nodes;
    }

    public function rawText(): string
    {
        return $this->span->rawText();
    }

    public function preambleText(): string
    {
        return implode('', array_map(static fn(Span $span): string => $span->rawText(), $this->preamble));
    }

    public function postambleText(): string
    {
        return implode('', array_map(static fn(Span $span): string => $span->rawText(), $this->postamble));
    }

    public function surroundedText(): string
    {
        return $this->preambleText() . $this->rawText() . $this->postambleText();
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    public function firstTagged(string $tag): ?self
    {
        foreach ($this->children as $child) {
            if ($child->hasTag($tag)) {
                return $child;
            }
        }

        return null;
    }

    /** @return list<AstNode> Immediate tagged children in source order. */
    public function allTagged(string $tag): array
    {
        return array_values(array_filter($this->children, static fn(self $child): bool => $child->hasTag($tag)));
    }

    public function child(int $index): ?self
    {
        return $this->children[$index] ?? null;
    }

    public function firstChild(): ?self
    {
        return $this->child(0);
    }

    public function lastChild(): ?self
    {
        return $this->child(count($this->children) - 1);
    }

    /** Search immediate children; a null group means any group. */
    public function firstNamed(string|int $name, ?string $group = null): ?self
    {
        foreach ($this->children as $child) {
            if ($child->name === $name && ($group === null || $child->group === $group)) {
                return $child;
            }
        }

        return null;
    }

    /** @return list<AstNode> Immediate children in source order. */
    public function allNamed(string|int $name, ?string $group = null): array
    {
        return array_values(array_filter(
            $this->children,
            static fn(self $child): bool => $child->name === $name && ($group === null || $child->group === $group),
        ));
    }

    /** Search immediate children; null selects children without a group. */
    public function firstInGroup(?string $group): ?self
    {
        foreach ($this->children as $child) {
            if ($child->group === $group) {
                return $child;
            }
        }

        return null;
    }

    /** @return list<AstNode> Immediate children in source order. */
    public function allInGroup(?string $group): array
    {
        return array_values(array_filter($this->children, static fn(self $child): bool => $child->group === $group));
    }

    /** @return iterable<AstNode> Depth-first pre-order traversal, excluding this node. */
    public function descendants(): iterable
    {
        $pending = array_reverse($this->children);
        while ($pending !== []) {
            $node = array_pop($pending);
            yield $node;
            foreach (array_reverse($node->children) as $child) {
                $pending[] = $child;
            }
        }
    }
}
