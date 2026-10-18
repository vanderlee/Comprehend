<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Mail\Grammar;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * SMTP Extension for Internationalized Email.
 *
 * SMTPUTF8 envelope addresses.
 *
 * Obsoletes RFC 5336. IDNA processing and SMTP extension negotiation are separate.
 *
 * @see Rfc5321 Extended by this RFC.
 * @see Rfc6532 UTF-8 header syntax.
 * @link https://www.rfc-editor.org/rfc/rfc6531.txt
 */
class Rfc6531 extends Rfc5321
{
    protected function definitions(): array
    {
        $rules = parent::definitions();
        $utf8 = (new Rfc6532())->rule('utf8_non_ascii');
        $rules['atom'] = C::first($rules['atom'], $utf8)->some();
        $rules['quoted_string'] = C::sequence('"', C::first(
            C::regex('/[\x20-\x21\x23-\x5b\x5d-\x7e]+/'),
            C::regex('/\\\\[\x20-\x7e]/'),
            $utf8,
        )->many(), '"');
        $label = C::sequence(
            C::first(C::regex('/[A-Za-z0-9]/'), $utf8),
            C::first(C::regex('/[A-Za-z0-9-]+/'), $utf8)->many(),
        )
            ->guard(static fn(ConcreteNode $node): bool => !str_ends_with($node->rawText(), '-'));
        $rules['domain'] = C::sequence($label, C::sequence('.', $label)->many());

        return $rules;
    }
}
