<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use InvalidArgumentException;
use Vanderlee\Comprehend\Source\Span;

/** Immutable recognition structure, independent of token and semantic projections. */
final readonly class ConcreteNode
{
    /** @param list<ConcreteNode> $children */
    public function __construct(
        public string $kind,
        public Span $span,
        public array $children = [],
        public bool $isTrivia = false,
        public string|int|null $name = null,
        public ?string $group = null,
        public bool $isTerminal = false
    )
    {
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
}
