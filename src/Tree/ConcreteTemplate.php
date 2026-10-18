<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use Vanderlee\Comprehend\Source\Source;

/** Source-free immutable syntax recipe for a successful, callback-free lexical pattern. */
final readonly class ConcreteTemplate
{
    /**
     * @param list<self> $children
     * @param list<string> $tags
     */
    private function __construct(
        private string $kind,
        private int $start,
        private int $end,
        private array $children,
        private bool $trivia,
        private string|int|null $name,
        private ?string $group,
        private bool $leaf,
        private array $tags,
    ) {
        // Retain only relative coordinates and immutable metadata, never a Source.
    }

    public static function from(ConcreteNode $node, int $origin): self
    {
        return new self(
            $node->kind,
            $node->span->start - $origin,
            $node->span->end - $origin,
            array_map(static fn(ConcreteNode $child): self => self::from($child, $origin), $node->children),
            $node->isTrivia,
            $node->name,
            $node->group,
            $node->semanticLeaf,
            $node->tags,
        );
    }

    public function instantiate(Source $source, int $origin): ConcreteNode
    {
        return new ConcreteNode(
            $this->kind,
            $source->span($origin + $this->start, $origin + $this->end),
            array_map(static fn(self $child): ConcreteNode => $child->instantiate($source, $origin), $this->children),
            $this->trivia,
            $this->name,
            $this->group,
            $this->leaf,
            $this->tags,
        );
    }
}
