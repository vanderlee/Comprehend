<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use InvalidArgumentException;
use Vanderlee\Comprehend\Source\Span;
use JsonSerializable;

/**
 * Retains recognized syntax, selected children, trivia and source spans. Provides the concrete
 * structure used by ParseResult, AstNode projection and shared tree serialization.
 */
final class ConcreteNode implements JsonSerializable
{
    use Serialization;

    /**
     * @param list<ConcreteNode> $children
     * @param list<string> $tags
     */
    public function __construct(
        public readonly string $kind,
        public readonly Span $span,
        public readonly array $children = [],
        public readonly bool $isTrivia = false,
        public readonly string|int|null $name = null,
        public readonly ?string $group = null,
        public readonly bool $semanticLeaf = false,
        public readonly array $tags = [],
    ) {
        // Empty lists already satisfy the structural invariants of plain leaves.
        if ($children !== [] || $tags !== []) {
            self::validateStructure($span, $children, $tags);
        }
    }

    /**
     * @param array<array-key, mixed> $children
     * @param array<array-key, mixed> $tags
     */
    private static function validateStructure(Span $span, array $children, array $tags): void
    {
        if (!array_is_list($tags)) {
            throw new InvalidArgumentException('Node tags must be a list');
        }
        foreach ($tags as $tag) {
            if (!is_string($tag) || $tag === '') {
                throw new InvalidArgumentException('Node tags must be nonempty strings');
            }
        }
        if (!array_is_list($children)) {
            throw new InvalidArgumentException('Concrete children must be a list');
        }
        // Intersections retain overlapping children, including spans longer than the parent.
        foreach ($children as $child) {
            if (!$child instanceof self || $child->span->source !== $span->source) {
                throw new InvalidArgumentException('Concrete children must be nodes from the same source');
            }
        }
    }

    /**
     * @return array{name: string|int|null, group: ?string, span: Span,
     *     children: list<self>, tags: list<string>}
     */
    protected function serializationData(): array
    {
        return [
            'name' => $this->name,
            'group' => $this->group,
            'span' => $this->span,
            'children' => $this->children,
            'tags' => $this->tags,
        ];
    }

    /** @return array<string, string|bool> */
    protected function serializationFields(): array
    {
        return ['kind' => $this->kind, 'trivia' => $this->isTrivia, 'semanticLeaf' => $this->semanticLeaf];
    }

    /** @return array<string, list<Span>> */
    protected function serializationSurroundings(): array
    {
        return [];
    }

    /**
     * @template T
     * @param NodeVisitor<T> $visitor
     * @return T
     */
    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitConcrete($this);
    }

    public function rawText(): string
    {
        return $this->span->rawText();
    }

    public function ast(): AstNode
    {
        return AstNode::fromConcrete($this);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
