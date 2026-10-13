<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Compatibility;

use LogicException;
use Vanderlee\Comprehend\Core\Token;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** Legacy token layout over immutable recognition snapshots. */
final class TokenProjection
{
    public static function project(ConcreteNode $node): Token
    {
        return self::projectNode($node, $node->span->source->sliceBytes(0, $node->span->source->byteLength()));
    }

    private static function projectNode(ConcreteNode $node, string $input): Token
    {
        $indexes = $node->tokenChildIndexes ?? array_keys($node->children);
        if ($node->tokenTransparent) {
            if (count($indexes) !== 1) {
                throw new LogicException('Transparent token nodes require exactly one projected child');
            }
            return self::projectNode($node->children[$indexes[0]], $input);
        }
        $children = [];
        if (!$node->isTerminal) {
            foreach ($indexes as $index) {
                $children[] = self::projectNode($node->children[$index], $input);
            }
        }
        $span = $node->span;
        return new Token($node->group, $node->name, $input,
            $span->start, $span->byteLength(), $children, $node->kind);
    }
}
