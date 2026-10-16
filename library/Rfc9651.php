<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Structured Field Values for HTTP.
 *
 * HTTP structured items, lists and dictionaries.
 *
 * Obsoletes RFC 8941. Recognizes wire syntax and numeric limits; value projection
 * and duplicate-key replacement follow the consuming field's interpretation.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9651.txt
 */
class Rfc9651 extends AbstractSpecification
{
    protected const ROOT_RULE = 'sf_list';

    protected function definitions(): array
    {
        $sp = C::regex('/ +/')->maybe();
        $ows = C::regex('/[ \t]+/')->maybe();

        return [
            'sf_integer' => C::regex('/-?[0-9]{1,15}/'),
            'sf_decimal' => C::regex('/-?[0-9]{1,12}\.[0-9]{1,3}/'),
            'sf_string' => C::regex('/"(?:[\x20-\x21\x23-\x5b\x5d-\x7e]|\\\\["\\\\])*"/'),
            'sf_token' => C::regex('/[A-Za-z*][A-Za-z0-9!#$%&\'*+\-.^_`|~:\/]*/'),
            'sf_binary' => C::sequence(':', C::regex('/[A-Za-z0-9+\/]*={0,2}/')->maybe()
                ->guard(static fn(ConcreteNode $node): bool => base64_decode($node->rawText(), true) !== false), ':'),
            'sf_boolean' => C::regex('/\?[01]/'),
            'sf_date' => C::sequence('@', $this->rule('sf_integer')),
            'sf_display_string' => C::regex('/%"(?:[\x20-\x21\x23-\x24\x26-\x7e]|%[0-9a-f]{2})*"/')
                ->guard(static fn(ConcreteNode $node): bool => mb_check_encoding(
                    rawurldecode(substr($node->rawText(), 2, -1)),
                    'UTF-8',
                )),
            'bare_item' => C::first(
                $this->rule('sf_decimal'),
                $this->rule('sf_integer'),
                $this->rule('sf_string'),
                $this->rule('sf_binary'),
                $this->rule('sf_boolean'),
                $this->rule('sf_date'),
                $this->rule('sf_display_string'),
                $this->rule('sf_token'),
            ),
            'key' => C::regex('/[a-z*][a-z0-9_.*-]*/'),
            'parameters' => C::sequence(
                ';',
                $sp,
                $this->rule('key'),
                C::sequence('=', $this->rule('bare_item'))->maybe(),
            )->many(),
            'item' => C::sequence($this->rule('bare_item'), $this->rule('parameters')),
            'sf_item' => C::sequence($sp, $this->rule('item'), $sp),
            'inner_list' => C::sequence(
                '(',
                $sp,
                C::sequence(
                    $this->rule('item'),
                    C::sequence(C::regex('/ +/'), $this->rule('item'))->many(),
                )->maybe(),
                $sp,
                ')',
                $this->rule('parameters'),
            ),
            'list_member' => C::first($this->rule('inner_list'), $this->rule('item')),
            'sf_list' => C::sequence($sp, C::sequence(
                $this->rule('list_member'),
                C::sequence($ows, ',', $ows, $this->rule('list_member'))->many(),
            )->maybe(), $sp),
            'dictionary_member' => C::sequence($this->rule('key'), C::first(
                C::sequence('=', $this->rule('list_member')),
                $this->rule('parameters'),
            )),
            'sf_dictionary' => C::sequence($sp, C::sequence(
                $this->rule('dictionary_member'),
                C::sequence($ows, ',', $ows, $this->rule('dictionary_member'))->many(),
            )->maybe(), $sp),
        ];
    }
}
