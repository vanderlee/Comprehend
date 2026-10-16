<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Library\Internal\Utf8NonAscii;

/**
 * Internationalized Email Headers.
 *
 * Email addresses and headers containing UTF-8 text.
 *
 * Obsoletes RFC 5335. UTF-8 sequences are checked explicitly in either source mode.
 *
 * @see Rfc5322 Extended by this RFC.
 * @link https://www.rfc-editor.org/rfc/rfc6532.txt
 */
class Rfc6532 extends Rfc5322
{
    protected function definitions(): array
    {
        $rules = parent::definitions();
        $rules['utf8_non_ascii'] = C::sequence(new Utf8NonAscii());
        foreach (['atext', 'qtext', 'ctext', 'dtext'] as $name) {
            $rules[$name] = C::first($rules[$name], $this->rule('utf8_non_ascii'))->some();
        }
        $rules['unstructured'] = C::first(
            $this->rule('fws'),
            C::regex('/[\x21-\x7e]+/'),
            $this->rule('utf8_non_ascii'),
        )->many();

        return $rules;
    }
}
