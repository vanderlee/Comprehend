<?php

/** @noinspection PhpUndefinedFieldInspection */

/**
 * RFC 2234 - Augmented BNF for Syntax Specifications: ABNF.
 *
 * Obsoleted by RFC 4234
 *
 * @see     https://tools.ietf.org/html/rfc2234
 */

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Parser\Parser;

require_once 'functions.php';

/**
 * @property-read Parser ALPHA  Alphabetic characters (upper- and lowercase)
 * @property-read Parser DIGIT  Decimal character
 * @property-read Parser HEXDIG Hexadecimal character
 * @property-read Parser BIT    Binary digit
 * @property-read Parser SB     Whitespace
 * @property-read Parser DQUOTE "
 */
class Rfc2234 extends AbstractRuleset
{
    protected static $name = 'Rfc2234';

    public function __construct($overwrites = [])
    {
        /*
         * Support rules.
         * These are not part of the published specification, but help make the published rules more manageable without
         * altering meaning or syntax. They exist outside the named scope.
         */
        $hexdigs = plus($this->HEXDIG);
        $digits = plus($this->DIGIT);
        $bits = plus($this->BIT);
        $c_wsps = star($this->c_wsp);

        /*
         * Normal rules
         */
        $rules = [
            /*
             * Core rules
             */
            'ALPHA'         => set('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'),
            'BIT'           => set('01'),
            'CHAR'          => range(0x01, 0x7F),
            'CR'            => char("\r"),
            'CRLF'          => text("\r\n"),
            'DIGIT'         => set('0123456789'),
            'DQUOTE'        => char('"'),
            'HEXDIG'        => set('0123456789ABCDEF'),
            'HTAB'          => char("\t"),
            'LF'            => char("\n"),
            'LWSP'          => regex("/(?:[ \t]|(?:\r\n[ \t]))*/"),
            'OCTET'         => range(0x00, 0xFF),
            'SP'            => char(' '),
            'VCHAR'         => range(0x21, 0x7E), // ['!', '~']
            'WSP'           => set(" \t"),

            /*
             * Definition of ABNF syntax (in reverse order for performance)
             */
            'prose_val'     => s('<', star(c(range(0x20, 0x3D), range(0x3F, 0x7E))), '>'),
            'hex_val'       => s('x', $hexdigs, opt(c(plus(['.', $hexdigs]), ['-', $hexdigs]))),
            'dec_val'       => s('d', $digits, opt(c(plus(['.', $digits]), ['-', $digits]))),
            'bin_val'       => s('b', $bits, opt(c(plus(['.', $bits]), ['-', $bits]))),
            'num_val'       => s('%', [$this->bin_val, $this->dec_val, $this->hex_val]),
            'char_val'      => s($this->DQUOTE, star(c(range(0x20, 0x21), range(0x23, 0x7E))), $this->DQUOTE),
            'option'        => s('[', $c_wsps, $this->alternation, $c_wsps, ']'),
            'group'         => s('(', $c_wsps, $this->alternation, $c_wsps, ')'),
            'element'       => c($this->rulename, $this->group, $this->option, $this->char_val, $this->num_val,
                $this->prose_val),
            'repeat'        => c($digits, [star($this->digit), '*', star($this->digit)]),
            'repetition'    => s(opt($this->repeat), $this->element),
            'concatenation' => s($this->repetition, star([plus($this->c_wsp), $this->repetition])),
            'alternation'   => s($this->concatenation, star([$c_wsps, '/', $c_wsps, $this->concatenation])),
            'comment'       => s(';', star(c($this->WSP, $this->VCHAR)), $this->CRLF),
            'c_nl'          => c($this->comment, $this->CRLF),
            'c_wsp'         => c($this->WSP, [$this->c_nl, $this->WSP]),
            'elements'      => [$this->alternation, $c_wsps],
            'defined_as'    => [$c_wsps, ['=', '=/'], $c_wsps],
            'rulename'      => s($this->ALPHA, star(c($this->ALPHA, $this->DIGIT, '-'))),
            'rule'          => s($this->rulename, $this->defined_as, $this->elements, $this->c_nl),
            'rulelist'      => plus(c($this->rule, [$c_wsps, $this->c_nl])),

            self::ROOT => $this->rulelist,
        ];

        parent::__construct(array_merge($rules, $overwrites));
    }
}
