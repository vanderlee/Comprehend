<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * DomainKeys Identified Mail (DKIM) Signatures.
 *
 * DKIM signature and DNS key tag lists.
 *
 * Obsoletes RFCs 4871 and 5672. Recognizes tag-list syntax and rejects duplicate tag
 * names; required tags, algorithms, canonicalization and cryptographic verification
 * must be checked by a DKIM implementation.
 *
 * @see Rfc5322 Email header components.
 * @link https://www.rfc-editor.org/rfc/rfc6376.txt
 */
class Rfc6376 extends AbstractSpecification
{
    protected const ROOT_RULE = 'tag_list';

    protected function definitions(): array
    {
        $fws = (new Rfc5322())->rule('fws')->maybe();

        return [
            'tag_name' => C::regex('/[A-Za-z][A-Za-z0-9_]*/'),
            'tag_value' => C::regex('/[\x21-\x3a\x3c-\x7e]+(?:'
                . '(?:[ \t]|\r\n(?=[ \t]))+[\x21-\x3a\x3c-\x7e]+)*/')->maybe(),
            'tag_spec' => C::sequence($fws, $this->rule('tag_name'), $fws, '=', $fws, $this->rule('tag_value'), $fws),
            'tag_list' => C::sequence(
                $this->rule('tag_spec'),
                C::sequence(';', $this->rule('tag_spec'))->many(),
                C::text(';')->maybe(),
            )->guard(static function (ConcreteNode $node): bool {
                preg_match_all('/(?:^|;)[ \t\r\n]*([A-Za-z][A-Za-z0-9_]*)[ \t\r\n]*=/', $node->rawText(), $matches);

                return count($matches[1]) === count(array_unique($matches[1]));
            }),
            'signature' => $this->rule('tag_list'),
            'key_record' => $this->rule('tag_list'),
            'signature_header' => C::sequence(
                C::text('DKIM-Signature:')->caseSensitive(false),
                $this->rule('tag_list'),
                "\r\n",
            ),
        ];
    }
}
