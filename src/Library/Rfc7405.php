<?php

declare (strict_types=1);
/**
 * RFC 7405 - Case-Sensitive String Support in ABNF.
 *
 * Updates RFC 5234
 *
 * @see     https://tools.ietf.org/html/rfc7405
 */

namespace Vanderlee\Comprehend\Library;

require_once 'functions.php';

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
            'quoted_string' => s(
                $this->rule('DQUOTE'),
                star(c(range(chr(0x20), chr(0x21)), range(chr(0x23), chr(0x7e)))),
                $this->rule('DQUOTE'),
            ),
            'case_sensitive_string' => s(text('%s'), $this->rule('quoted_string')),
            'case_insensitive_string' => s(opt(text('%i')), $this->rule('quoted_string')),
            'char_val' => c($this->rule('case_insensitive_string'), $this->rule('case_sensitive_string')),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
