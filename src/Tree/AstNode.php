<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use InvalidArgumentException;
use Vanderlee\Comprehend\Source\Span;

/** Named structure; raw source remains independent of semantic results. */
final readonly class AstNode
{
    /** @param list<AstNode> $children A null name denotes the synthetic root. */
    public function __construct(
        public string|int|null $name,
        public ?string $group,
        public Span $span,
        public array $children = []
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

    public function rawText(): string
    {
        return $this->span->rawText();
    }

    public static function fromConcrete(ConcreteNode $root): self
    {
        $nodes = self::project($root);
        return !$root->isTrivia && $root->name !== null ? $nodes[0]
            : new self(null, null, $root->span, $nodes);
    }

    /** @return list<AstNode> */
    private static function project(ConcreteNode $node): array
    {
        if ($node->isTrivia) { return []; }
        $children = [];
        if (!$node->isTerminal) {
            foreach ($node->children as $child) {
                foreach (self::project($child) as $projected) { $children[] = $projected; }
            }
        }
        return $node->name === null ? $children
            : [new self($node->name, $node->group, $node->span, $children)];
    }
}
