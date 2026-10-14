<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use InvalidArgumentException;
use Vanderlee\Comprehend\Source\Span;

/** Immutable recognition structure, independent of semantic projections. */
final readonly class ConcreteNode implements \JsonSerializable
{
    use Serialization;

    /** @param list<ConcreteNode> $children */
    public function __construct(
        public string $kind,
        public Span $span,
        public array $children = [],
        public bool $isTrivia = false,
        public string|int|null $name = null,
        public ?string $group = null,
        public bool $semanticLeaf = false,
        public array $tags = [],
    ) {
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
        foreach ($children as $child) {
            if (!$child instanceof self || $child->span->source !== $span->source) {
                throw new InvalidArgumentException('Concrete children must be nodes from the same source');
            }
        }
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
