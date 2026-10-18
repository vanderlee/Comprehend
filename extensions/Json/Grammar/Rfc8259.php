<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Json\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * The JavaScript Object Notation (JSON) Data Interchange Format.
 *
 * JSON documents and reusable values.
 *
 * Obsoletes RFC 7159 (which obsoletes RFC 4627).
 * Preserves duplicate object members and numeric spelling; decoding is a separate operation.
 *
 * @link https://www.rfc-editor.org/rfc/rfc8259.txt
 */
class Rfc8259 extends AbstractSpecification
{
    protected const ROOT_RULE = 'json_text';

    protected function definitions(): array
    {
        $ws = $this->rule('ws');

        return [
            'ws' => C::regex('/[ \t\r\n]+/')->maybe(),
            'string' => C::regex('/"(?:[^"\\\\\x00-\x1f]|\\\\(?:["\\\\\/bfnrt]|u[0-9A-Fa-f]{4}))*"/')
                ->guard(static fn(ConcreteNode $node): bool => mb_check_encoding($node->rawText(), 'UTF-8')),
            'number' => C::regex('/-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/'),
            'value' => C::first(
                $this->rule('object'),
                $this->rule('array'),
                $this->rule('string'),
                $this->rule('number'),
                C::text('true')->caseSensitive(),
                C::text('false')->caseSensitive(),
                C::text('null')->caseSensitive(),
            ),
            'member' => C::sequence($this->rule('string'), $ws, ':', $ws, $this->rule('value')),
            'object' => C::sequence(
                '{',
                $ws,
                C::sequence(
                    $this->rule('member'),
                    C::sequence($ws, ',', $ws, $this->rule('member'))->many(),
                )->maybe(),
                $ws,
                '}',
            ),
            'array' => C::sequence(
                '[',
                $ws,
                C::sequence(
                    $this->rule('value'),
                    C::sequence($ws, ',', $ws, $this->rule('value'))->many(),
                )->maybe(),
                $ws,
                ']',
            ),
            'json_text' => C::sequence($ws, $this->rule('value'), $ws),
        ];
    }
}
