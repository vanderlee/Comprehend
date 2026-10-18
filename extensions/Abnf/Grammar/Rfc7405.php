<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Abnf\Grammar;

use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Case-Sensitive String Support in ABNF.
 *
 * Case-sensitive strings in ABNF grammars.
 *
 * Updates RFC 5234
 *
 * Extends Rfc5234 with explicit case-sensitive and case-insensitive ABNF string syntax.
 * Overrides string productions while retaining the inherited core and grammar rules.
 *
 * @see Rfc5234 Updated by this RFC.
 * @see https://www.rfc-editor.org/rfc/rfc7405
 * @link https://www.rfc-editor.org/rfc/rfc7405.txt
 */
class Rfc7405 extends Rfc5234
{
    /**
     * @var string
     */
    protected static $name = 'Rfc7405';

    /**
     * @param array<string, C|Parser|Definition|callable> $overwrites
     */
    public function __construct($overwrites = [])
    {
        $rules = [
            /*
             * 2.2 ABNF Definition of ABNF - char-val
             */
            'quoted_string' => C::sequence(
                $this->rule('DQUOTE'),
                C::choice(C::range(chr(0x20), chr(0x21)), C::range(chr(0x23), chr(0x7e)))->many(),
                $this->rule('DQUOTE'),
            ),
            'case_sensitive_string' => C::sequence(C::text('%s'), $this->rule('quoted_string')),
            'case_insensitive_string' => C::sequence(
                C::text('%i')->maybe(),
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
