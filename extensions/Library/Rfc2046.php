<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use InvalidArgumentException;
use Vanderlee\Comprehend\Comprehend as C;

/**
 * Multipurpose Internet Mail Extensions (MIME) Part Two: Media Types.
 *
 * MIME media types, boundaries and multipart bodies.
 *
 * Import multipart_body with its boundary argument. Boundary selection comes from
 * the containing Content-Type header; it is never inferred from arbitrary body text.
 *
 * @see Rfc2045 MIME header components.
 * @link https://www.rfc-editor.org/rfc/rfc2046.txt
 */
class Rfc2046 extends Rfc2045
{
    protected function definitions(): array
    {
        return array_replace(parent::definitions(), [
            'boundary' => C::regex('/[0-9A-Za-z\'()+_,.\/:=? -]{0,69}[0-9A-Za-z\'()+_,.\/:=?-]/'),
            'multipart_body' => static function (string $boundary): C {
                if (preg_match('/\A[0-9A-Za-z\'()+_,.\/:=? -]{0,69}[0-9A-Za-z\'()+_,.\/:=?-]\z/', $boundary) !== 1) {
                    throw new InvalidArgumentException('Invalid MIME boundary');
                }
                $quoted = preg_quote($boundary, '~');
                $padding = '[ \t]*\r\n';
                $part = '(?:(?!\r\n--' . $quoted . '(?:--|[ \t]*\r\n))[\s\S])*';

                return C::regex('~(?:(?!^--' . $quoted . ')[\s\S])*?^--' . $quoted . $padding
                    . $part . '(?:\r\n--' . $quoted . $padding . $part . ')*\r\n--' . $quoted
                    . '--[ \t]*(?:\r\n[\s\S]*)?~m');
            },
        ]);
    }
}
