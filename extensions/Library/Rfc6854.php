<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use Vanderlee\Comprehend\Comprehend as C;

/**
 * Update to Internet Message Format to Allow Group Syntax in the "From:" and "Sender:" Header Fields.
 *
 * Email originator headers with group addresses.
 *
 * @see Rfc5322 Updated by this RFC.
 * @link https://www.rfc-editor.org/rfc/rfc6854.txt
 */
class Rfc6854 extends Rfc5322
{
    protected function definitions(): array
    {
        return array_replace(parent::definitions(), [
            'from' => C::sequence(C::text('From:')->caseSensitive(false), $this->rule('address_list'), "\r\n"),
            'sender' => C::sequence(C::text('Sender:')->caseSensitive(false), $this->rule('address'), "\r\n"),
        ]);
    }
}
