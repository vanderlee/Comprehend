<?php /** @noinspection PhpUndefinedFieldInspection */

/**
 * RFC 7405 - Case-Sensitive String Support in ABNF
 *
 * Updated by RFC 7405
 *
 * @see https://tools.ietf.org/html/rfc7405
 * @package vanderlee\comprehend\library
 */

namespace vanderlee\comprehend\library;

require_once 'functions.php';

use vanderlee\comprehend\parser\Parser;

/**
 * @property-read Parser DQUOTE
 * @property-read Parser case_sensitive_string
 */
class Rfc7405 extends Rfc2234
{
    protected static $name = 'Rfc7405';

    public function __construct($overwrites = [])
    {
        $rules = [
            /*
             * 2.2 ABNF Definition of ABNF - char-val
             */
            'quoted_string'           => s($this->DQUOTE, star(c(range(0x20, 0x21), range(0x23, 0x7E))), $this->DQUOTE),
            'case_sensitive_string'   => s('%s', $this->quoted_string),
            'case_insensitive_string' => s(opt('%i'), $this->quoted_string),
            'char_val'                => c($this->case_insensitive_string, $this->case_sensitive_string),
        ];

        parent::__construct(array_merge($rules, $overwrites));
    }
}