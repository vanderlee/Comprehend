<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Library\Internal\UnicodeEscapes;
use Vanderlee\Comprehend\Library\Internal\YamlBlock;
use Vanderlee\Comprehend\Library\Internal\YamlCharacters;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * YAML Ain't Markup Language (YAML) Version 1.2, Revision 1.2.2.
 *
 * YAML streams, block collections, flow collections and scalars.
 *
 * Preserves source syntax. Scalar resolution, tag construction, alias binding and
 * mapping-key equality are separate semantic operations. Indentation is recognized
 * by a source-aware Parser on the same session and byte-coordinate runtime.
 *
 * @link https://yaml.org/spec/1.2.2/
 */
class Yaml extends AbstractSpecification
{
    protected const ROOT_RULE = 'stream';

    protected function definitions(): array
    {
        $ws = $this->rule('flow_space');
        $node = $this->rule('flow_node');
        $pair = C::sequence($node->maybe(), $ws, ':', $ws, $node->maybe());

        $rules = [
            'flow_space' => C::regex('/(?:[ \t\r\n]+|(?<!\S)#[^\r\n]*(?:\r\n|\r|\n|$))+/')->maybe(),
            'single_quoted' => C::regex("/'(?:[^']|'')*'/"),
            'double_quoted' => C::regex('/"(?:[^"\\\\]|\\\\(?:[0abtnvfre \t"\/\\\\N_LP]|x[0-9a-fA-F]{2}|'
                . 'u[0-9a-fA-F]{4}|U[0-9a-fA-F]{8}|\r\n|\r|\n))*"/')
                ->guard(static fn(ConcreteNode $node): bool => UnicodeEscapes::valid($node->rawText(), false)),
            'plain_scalar' => C::regex('/(?![-?:](?:[ \t\r\n,\[\]{}]|$))(?![\[\]{},&*!|>\'"%@`#])'
                . '(?:[^ \t\r\n,\[\]{}:#]|:(?![ \t\r\n,\[\]{}]|$)|(?<![ \t])#)+'
                . '(?:[ \t\r\n]+(?:[^ \t\r\n,\[\]{}:#]|:(?![ \t\r\n,\[\]{}]|$)|(?<![ \t])#)+)*/'),
            'anchor' => C::regex('/&[^\s\[\]{},]+/'),
            'alias' => C::regex('/\*[^\s\[\]{},]+/'),
            'tag' => C::regex('/!(?:<[^\s<>]+>|(?:[A-Za-z0-9-]+!)?[^\s\[\]{},]*)/'),
            'property' => C::first($this->rule('anchor'), $this->rule('tag')),
            'properties' => C::sequence(
                $this->rule('property'),
                C::sequence(C::regex('/[ \t]+/'), $this->rule('property'))->maybe(),
            ),
            'flow_pair' => $pair,
            'flow_sequence_pair' => $pair->guard(static fn(ConcreteNode $node): bool
                => strpbrk($node->children[0]->rawText() . $node->children[1]->rawText(), "\r\n") === false),
            'explicit_flow_pair' => C::sequence(
                '?',
                $ws,
                $node->maybe(),
                C::sequence($ws, ':', $ws, $node->maybe())->maybe(),
            ),
            'flow_sequence' => C::sequence('[', $ws, C::sequence(
                C::first($this->rule('explicit_flow_pair'), $this->rule('flow_sequence_pair'), $node),
                C::sequence(
                    $ws,
                    ',',
                    $ws,
                    C::first($this->rule('explicit_flow_pair'), $this->rule('flow_sequence_pair'), $node),
                )->many(),
                C::sequence($ws, ',')->maybe(),
            )->maybe(), $ws, ']'),
            'flow_mapping' => C::sequence('{', $ws, C::sequence(
                C::first($this->rule('explicit_flow_pair'), $this->rule('flow_pair'), $node),
                C::sequence(
                    $ws,
                    ',',
                    $ws,
                    C::first($this->rule('explicit_flow_pair'), $this->rule('flow_pair'), $node),
                )->many(),
                C::sequence($ws, ',')->maybe(),
            )->maybe(), $ws, '}'),
            'flow_content' => C::first(
                $this->rule('flow_sequence'),
                $this->rule('flow_mapping'),
                $this->rule('single_quoted'),
                $this->rule('double_quoted'),
                $this->rule('alias'),
                $this->rule('plain_scalar'),
            ),
            'flow_node' => C::first(
                C::sequence(
                    $this->rule('properties'),
                    C::sequence($ws, $this->rule('flow_content'))->maybe(),
                ),
                $this->rule('flow_content'),
            ),
            'stream' => new YamlBlock($node->parser(), stream: true),
            'block_node' => new YamlBlock($node->parser()),
        ];
        foreach (['single_quoted', 'double_quoted', 'plain_scalar', 'anchor', 'alias', 'tag'] as $name) {
            $rules[$name] = $rules[$name]->guard(static fn(ConcreteNode $node): bool
                => YamlCharacters::valid($node->rawText()));
        }

        return $rules;
    }
}
