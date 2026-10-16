<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Library\Internal\UnicodeEscapes;
use Vanderlee\Comprehend\Library\Internal\Utf8NonAscii;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * JSONPath: Query Expressions for JSON.
 *
 * JSONPath selection and filter expressions.
 *
 * Recognizes expression syntax and the standard functions' argument/result types.
 * Query evaluation and type declarations for extension functions are separate operations.
 *
 * @see Rfc8259 JSON values.
 * @link https://www.rfc-editor.org/rfc/rfc9535.txt
 */
class Rfc9535 extends AbstractSpecification
{
    protected const ROOT_RULE = 'jsonpath_query';

    protected function definitions(): array
    {
        $ws = C::regex('/[ \t\r\n]+/')->maybe();
        $json = new Rfc8259();

        return [
            'member_name' => C::sequence(
                C::first(C::regex('/[A-Za-z_]/'), new Utf8NonAscii()),
                C::first(C::regex('/[A-Za-z0-9_]+/'), new Utf8NonAscii())->many(),
            ),
            'string_literal' => C::first(
                $json->rule('string'),
                C::regex("~'(?:[^'\\\\\x00-\x1f]|\\\\(?:['\\\\/bfnrt]|u[0-9a-fA-F]{4}))*'~"),
            )
                ->guard(static fn(ConcreteNode $node): bool => UnicodeEscapes::valid($node->rawText(), true)),
            'int' => C::regex('/(?:0|-?[1-9][0-9]*)/')->guard(static function (ConcreteNode $node): bool {
                $digits = ltrim($node->rawText(), '-');

                return strlen($digits) < 16 || (strlen($digits) === 16 && strcmp($digits, '9007199254740991') <= 0);
            }),
            'slice_selector' => C::sequence(
                $this->rule('int')->maybe(),
                $ws,
                ':',
                $ws,
                $this->rule('int')->maybe(),
                C::sequence($ws, ':', $ws, $this->rule('int')->maybe())->maybe(),
            ),
            'selector' => C::first(
                $this->rule('slice_selector'),
                $this->rule('string_literal'),
                C::text('*'),
                C::sequence('?', $ws, $this->rule('logical_expr')),
                $this->rule('int'),
            ),
            'bracketed_selection' => C::sequence(
                '[',
                $ws,
                $this->rule('selector'),
                C::sequence($ws, ',', $ws, $this->rule('selector'))->many(),
                $ws,
                ']',
            ),
            'child_segment' => C::first(
                $this->rule('bracketed_selection'),
                C::sequence('.', C::first(C::text('*'), $this->rule('member_name'))),
            ),
            'descendant_segment' => C::sequence('..', C::first(
                $this->rule('bracketed_selection'),
                C::text('*'),
                $this->rule('member_name'),
            )),
            'segment' => C::first($this->rule('descendant_segment'), $this->rule('child_segment')),
            'jsonpath_query' => C::sequence('$', C::sequence($ws, $this->rule('segment'))->many()),
            'filter_query' => C::sequence(C::set('$@'), C::sequence($ws, $this->rule('segment'))->many()),
            'singular_query' => C::sequence(C::set('$@'), C::sequence($ws, C::first(
                C::sequence('.', $this->rule('member_name')),
                C::sequence('[', $ws, C::first($this->rule('string_literal'), $this->rule('int')), $ws, ']'),
            ))->many()),
            'literal' => C::first(
                $this->rule('string_literal'),
                $json->rule('number'),
                C::text('true'),
                C::text('false'),
                C::text('null'),
            ),
            'extension_function_expr' => C::sequence(
                C::not(C::regex('/(?:count|length|value|match|search)(?=\()/')),
                C::regex('/[a-z][a-z0-9_]*/'),
                '(',
                $ws,
                C::sequence(
                    $this->rule('function_argument'),
                    C::sequence($ws, ',', $ws, $this->rule('function_argument'))->many(),
                )->maybe(),
                $ws,
                ')',
            ),
            'nodes_argument' => C::first($this->rule('filter_query'), $this->rule('extension_function_expr')),
            'value_argument' => C::first(
                $this->rule('literal'),
                $this->rule('singular_query'),
                $this->rule('value_function_expr'),
                $this->rule('extension_function_expr'),
            ),
            'value_function_expr' => C::first(
                C::sequence(
                    C::first(C::text('count'), C::text('value')),
                    '(',
                    $ws,
                    $this->rule('nodes_argument'),
                    $ws,
                    ')',
                ),
                C::sequence('length(', $ws, $this->rule('value_argument'), $ws, ')'),
            ),
            'logical_function_expr' => C::sequence(
                C::first(C::text('match'), C::text('search')),
                '(',
                $ws,
                $this->rule('value_argument'),
                $ws,
                ',',
                $ws,
                $this->rule('value_argument'),
                $ws,
                ')',
            ),
            'function_expr' => C::first(
                $this->rule('value_function_expr'),
                $this->rule('logical_function_expr'),
                $this->rule('extension_function_expr'),
            ),
            'function_argument' => C::longest(
                $this->rule('logical_expr'),
                $this->rule('literal'),
                $this->rule('function_expr'),
            ),
            'comparable' => C::first(
                $this->rule('literal'),
                $this->rule('value_function_expr'),
                $this->rule('extension_function_expr'),
                $this->rule('singular_query'),
            ),
            'comparison_expr' => C::sequence(
                $this->rule('comparable'),
                $ws,
                C::regex('/(?:==|!=|<=|>=|<|>)/'),
                $ws,
                $this->rule('comparable'),
            ),
            'basic_expr' => C::sequence(C::sequence('!', $ws)->maybe(), C::first(
                C::sequence('(', $ws, $this->rule('logical_expr'), $ws, ')'),
                $this->rule('comparison_expr'),
                $this->rule('logical_function_expr'),
                $this->rule('extension_function_expr'),
                $this->rule('filter_query'),
            )),
            'logical_and_expr' => C::sequence(
                $this->rule('basic_expr'),
                C::sequence($ws, '&&', $ws, $this->rule('basic_expr'))->many(),
            ),
            'logical_expr' => C::sequence(
                $this->rule('logical_and_expr'),
                C::sequence($ws, '||', $ws, $this->rule('logical_and_expr'))->many(),
            ),
        ];
    }
}
