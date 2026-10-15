<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use Vanderlee\Comprehend\Source\Span;

/** @template-covariant T */
interface NodeVisitor
{
    /** @return T */
    public function visitConcrete(ConcreteNode $node): mixed;

    /** @return T */
    public function visitAst(AstNode $node): mixed;

    /**
     * Supports the Serialization trait's explicit hooks for custom node types.
     * @param array{name: string|int|null, group: ?string, span: Span,
     *     children: list<AstNode|ConcreteNode>, tags: list<string>} $node
     * @param array<string, string|bool> $fields
     * @param array<string, list<Span>> $surroundings
     * @return T
     */
    public function visitData(array $node, array $fields, array $surroundings): mixed;
}
