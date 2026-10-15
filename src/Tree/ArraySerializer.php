<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Tree;

use Vanderlee\Comprehend\Source\Span;

/** @internal
 * @implements NodeVisitor<array<string, mixed>>
 */
final class ArraySerializer implements NodeVisitor
{
    use SerializationData;

    /** @return array<string, mixed> */
    public function visitConcrete(ConcreteNode $node): array
    {
        return $this->visitData(self::data($node), [
            'kind' => $node->kind,
            'trivia' => $node->isTrivia,
            'semanticLeaf' => $node->semanticLeaf,
        ], []);
    }

    /** @return array<string, mixed> */
    public function visitAst(AstNode $node): array
    {
        return $this->visitData(self::data($node), [], [
            'preamble' => $node->preamble,
            'postamble' => $node->postamble,
        ]);
    }


    /**
     * @param array{name: string|int|null, group: ?string, span: Span,
     *     children: list<AstNode|ConcreteNode>, tags: list<string>} $node
     * @param array<string, string|bool> $fields
     * @param array<string, list<Span>> $surroundings
     * @return array<string, mixed>
     */
    public function visitData(array $node, array $fields, array $surroundings): array
    {
        $data = [
            'name' => $node['name'],
            'group' => $node['group'],
            ...self::spanArray($node['span']),
            'children' => array_map(fn($child): array => $child->accept($this), $node['children']),
            ...$fields,
        ];
        if ($node['tags'] !== []) {
            $data['tags'] = $node['tags'];
        }
        foreach ($surroundings as $side => $spans) {
            if ($spans !== []) {
                $data[$side] = array_map(self::spanArray(...), $spans);
            }
        }

        return $data;
    }

    /**
     * @return array{start: int, end: int, text: string, textEncoding: 'utf8'|'base64'}
     */
    private static function spanArray(Span $span): array
    {
        $raw = $span->rawText();
        $utf8 = mb_check_encoding($raw, 'UTF-8');

        return [
            'start' => $span->start,
            'end' => $span->end,
            'text' => $utf8 ? $raw : base64_encode($raw),
            'textEncoding' => $utf8 ? 'utf8' : 'base64',
        ];
    }
}
