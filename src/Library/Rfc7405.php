<?php

/** @noinspection PhpUndefinedFieldInspection */

/**
 * RFC 7405 - Case-Sensitive String Support in ABNF.
 *
 * Updates RFC 5234
 *
 * @see     https://tools.ietf.org/html/rfc7405
 */

namespace Vanderlee\Comprehend\Library;

require_once 'functions.php';

use Vanderlee\Comprehend\Parser\Parser;

/**
 * @property-read Parser case_sensitive_string
 * @property-read Parser quoted_string
 * @property-read Parser case_insensitive_string
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
            'quoted_string'           => s($this->DQUOTE, star(c(range(0x20, 0x21), range(0x23, 0x7E))), $this->DQUOTE),
            'case_sensitive_string'   => s('%s', $this->quoted_string),
            'case_insensitive_string' => s(opt('%i'), $this->quoted_string),
            'char_val'                => c($this->case_insensitive_string, $this->case_sensitive_string),
        ];

        parent::__construct(array_merge($rules, $overwrites));
    }
}
