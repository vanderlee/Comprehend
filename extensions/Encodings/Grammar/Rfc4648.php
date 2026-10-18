<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Encodings\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;

/**
 * The Base16, Base32, and Base64 Data Encodings.
 *
 * Hexadecimal, Base32, Base64 and URL-safe Base64 text.
 *
 * Obsoletes RFC 3548. Padded rules enforce zero pad bits. Unpadded rules are
 * available explicitly for protocols that permit padding to be omitted.
 *
 * @link https://www.rfc-editor.org/rfc/rfc4648.txt
 */
class Rfc4648 extends AbstractSpecification
{
    protected const ROOT_RULE = 'base64';

    protected function definitions(): array
    {
        $base64 = '(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}[AEIMQUYcgkosw048]=|[A-Za-z0-9+/][AQgw]==)?';
        $base32 = '(?:[A-Z2-7]{8})*(?:[A-Z2-7]{6}[AEIMQUY4]=|[A-Z2-7]{4}[ACEGIKMOQSUWY246]===|'
            . '[A-Z2-7]{3}[AQ]====|[A-Z2-7][AEIMQUY4]======)?';

        return [
            'base16' => C::regex('/(?:[0-9A-F]{2})+/i')->maybe(),
            'base64' => C::regex('~' . str_replace('~', '\\~', $base64) . '~')->maybe(),
            'base64url' => C::regex('~' . str_replace('+/', '_-', $base64) . '~')->maybe(),
            'base32' => C::regex('/' . $base32 . '/i')->maybe(),
            'base32hex' => C::regex('/(?:[0-9A-V]{8})*(?:[0-9A-V]{6}[048CGKOS]=|[0-9A-V]{4}[02468ACEGIKMOQSU]===|'
                . '[0-9A-V]{3}[0G]====|[0-9A-V][048CGKOS]======)?/i')->maybe(),
            'base64_unpadded' => C::regex('~(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}[AEIMQUYcgkosw048]|'
                . '[A-Za-z0-9+/][AQgw])?~')->maybe(),
            'base64url_unpadded' => C::regex('~(?:[A-Za-z0-9_-]{4})*(?:[A-Za-z0-9_-]{2}[AEIMQUYcgkosw048]|'
                . '[A-Za-z0-9_-][AQgw])?~')->maybe(),
        ];
    }
}
