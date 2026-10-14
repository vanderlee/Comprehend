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
}
