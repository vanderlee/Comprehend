<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use InvalidArgumentException;
use JsonSerializable;
use Vanderlee\Comprehend\Source\Span;

/** Named structure; raw source remains independent of semantic results. */
final readonly class AstNode implements JsonSerializable
{
    use Serialization;

    /** @param list<AstNode> $children A null name denotes the synthetic root. */
    public function __construct(
        public string|int|null $name,
        public ?string $group,
        public Span $span,
        public array $children = [],
    ) {
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

        return new self(null, null, $root->span, $nodes);
    }

    /** @return list<AstNode> */
    private static function project(ConcreteNode $node): array
    {
        if ($node->isTrivia) {
            return [];
        }
        $children = [];
        if (!$node->semanticLeaf) {
            foreach ($node->children as $child) {
                array_push($children, ...self::project($child));
            }
        }
        return $node->name === null ? $children : [new self($node->name, $node->group, $node->span, $children)];
    }

    public function rawText(): string
    {
        return $this->span->rawText();
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
