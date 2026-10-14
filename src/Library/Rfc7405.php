<?php

declare(strict_types=1);

/**
 * RFC 7405 - Case-Sensitive String Support in ABNF.
 *
 * Updates RFC 5234
 *
 * @see     https://tools.ietf.org/html/rfc7405
 */

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;

/**
 */
class Rfc7405 extends Rfc5234
{
    protected static $name = 'Rfc7405';

    public function __construct($overwrites = [])
    {
        $rules = [
            /*
             * 2.2 ABNF Definition of ABNF - char-val
             */
            'quoted_string' => C::sequence(
                $this->rule('DQUOTE'),
                C::choice(C::range(chr(0x20), chr(0x21)), C::range(chr(0x23), chr(0x7e)))->star(),
                $this->rule('DQUOTE'),
            ),
            'case_sensitive_string' => C::sequence(C::text('%s'), $this->rule('quoted_string')),
            'case_insensitive_string' => C::sequence(
                C::text('%i')->optional(),
                $this->rule('quoted_string'),
            ),
            'char_val' => C::choice(
                $this->rule('case_insensitive_string'),
                $this->rule('case_sensitive_string'),
            ),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
