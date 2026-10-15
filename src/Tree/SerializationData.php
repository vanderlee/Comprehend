<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use Vanderlee\Comprehend\Source\Span;

/** @internal Common source data for format visitors. */
trait SerializationData
{
    /** @return array{name: string|int|null, group: ?string, span: Span,
     *     children: list<AstNode|ConcreteNode>, tags: list<string>} */
    private static function data(AstNode|ConcreteNode $node): array
    {
        return [
            'name' => $node->name,
            'group' => $node->group,
            'span' => $node->span,
            'children' => $node->children,
            'tags' => $node->tags,
        ];
    }
}
