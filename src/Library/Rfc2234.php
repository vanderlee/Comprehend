<?php

declare(strict_types=1);

/**
 * RFC 2234 - Augmented BNF for Syntax Specifications: ABNF.
 *
 * Obsoleted by RFC 4234
 *
 * @see     https://tools.ietf.org/html/rfc2234
 */

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Comprehend as C;

/**
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
        $hexdigs = C::of($this->rule('HEXDIG'))->repeat(1);
        $digits = C::of($this->rule('DIGIT'))->repeat(1);
        $bits = C::of($this->rule('BIT'))->repeat(1);
        $c_wsps = C::of($this->rule('c_wsp'))->repeat();
        /*
         * Normal rules
         */
        $rules = [
            /*
             * Core rules
             */
            'ALPHA' => C::set('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'),
            'BIT' => C::set('01'),
            'CHAR' => C::range(chr(0x1), chr(0x7f)),
            'CR' => C::char("\r"),
            'CRLF' => C::text("\r\n"),
            'DIGIT' => C::set('0123456789'),
            'DQUOTE' => C::char('"'),
            'HEXDIG' => C::set('0123456789ABCDEF'),
            'HTAB' => C::char("\t"),
            'LF' => C::char("\n"),
            'LWSP' => C::regex("/(?:[ \t]|(?:\r\n[ \t]))*/"),
            'OCTET' => C::range(chr(0x0), chr(0xff)),
            'SP' => C::char(' '),
            'VCHAR' => C::range(chr(0x21), chr(0x7e)),
            // ['!', '~']
            'WSP' => C::set(" \t"),
            /*
             * Definition of ABNF syntax (in reverse order for performance)
             */
            'prose_val' => C::sequence(
                C::text('<'),
                C::choice(C::range(chr(0x20), chr(0x3d)), C::range(chr(0x3f), chr(0x7e)))->repeat(),
                C::text('>'),
            ),
            'hex_val' => C::sequence(
                C::text('x'),
                $hexdigs,
                C::choice(
                    C::sequence(C::text('.'), $hexdigs)->repeat(1),
                    C::sequence(C::text('-'), $hexdigs),
                )->repeat(0, 1),
            ),
            'dec_val' => C::sequence(
                C::text('d'),
                $digits,
                C::choice(
                    C::sequence(C::text('.'), $digits)->repeat(1),
                    C::sequence(C::text('-'), $digits),
                )->repeat(0, 1),
            ),
            'bin_val' => C::sequence(
                C::text('b'),
                $bits,
                C::choice(
                    C::sequence(C::text('.'), $bits)->repeat(1),
                    C::sequence(C::text('-'), $bits),
                )->repeat(0, 1),
            ),
            'num_val' => C::sequence(
                C::text('%'),
                C::choice($this->rule('bin_val'), $this->rule('dec_val'), $this->rule('hex_val')),
            ),
            'char_val' => C::sequence(
                $this->rule('DQUOTE'),
                C::choice(C::range(chr(0x20), chr(0x21)), C::range(chr(0x23), chr(0x7e)))->repeat(),
                $this->rule('DQUOTE'),
            ),
            'option' => C::sequence(
                C::text('['),
                $c_wsps,
                $this->rule('alternation'),
                $c_wsps,
                C::text(']'),
            ),
            'group' => C::sequence(
                C::text('('),
                $c_wsps,
                $this->rule('alternation'),
                $c_wsps,
                C::text(')'),
            ),
            'element' => C::choice(
                $this->rule('rulename'),
                $this->rule('group'),
                $this->rule('option'),
                $this->rule('char_val'),
                $this->rule('num_val'),
                $this->rule('prose_val'),
            ),
            'repeat' => C::choice(
                $digits,
                C::sequence(
                    C::of($this->rule('DIGIT'))->repeat(),
                    C::text('*'),
                    C::of($this->rule('DIGIT'))->repeat(),
                ),
            ),
            'repetition' => C::sequence(
                C::of($this->rule('repeat'))->repeat(0, 1),
                $this->rule('element'),
            ),
            'concatenation' => C::sequence(
                $this->rule('repetition'),
                C::sequence(C::of($this->rule('c_wsp'))->repeat(1), $this->rule('repetition'))->repeat()
            ),
            'alternation' => C::sequence(
                $this->rule('concatenation'),
                C::sequence($c_wsps, C::text('/'), $c_wsps, $this->rule('concatenation'))->repeat()
            ),
            'comment' => C::sequence(
                C::text(';'),
                C::choice($this->rule('WSP'), $this->rule('VCHAR'))->repeat(),
                $this->rule('CRLF'),
            ),
            'c_nl' => C::choice($this->rule('comment'), $this->rule('CRLF')),
            'c_wsp' => C::choice(
                $this->rule('WSP'),
                C::sequence($this->rule('c_nl'), $this->rule('WSP')),
            ),
            'elements' => C::sequence($this->rule('alternation'), $c_wsps),
            'defined_as' => C::sequence(
                $c_wsps,
                C::choice(C::text('='), C::text('=/')),
                $c_wsps,
            ),
            'rulename' => C::sequence(
                $this->rule('ALPHA'),
                C::choice($this->rule('ALPHA'), $this->rule('DIGIT'), C::text('-'))->repeat(),
            ),
            'rule' => C::sequence(
                $this->rule('rulename'),
                $this->rule('defined_as'),
                $this->rule('elements'),
                $this->rule('c_nl')
            ),
            'rulelist' => C::choice(
                $this->rule('rule'),
                C::sequence($c_wsps, $this->rule('c_nl')),
            )->repeat(1),
            self::ROOT => $this->rule('rulelist'),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
