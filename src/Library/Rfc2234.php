<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Augmented BNF for Syntax Specifications: ABNF.
 *
 * ABNF grammar syntax.
 *
 * Obsoleted by RFC 4234
 *
 * Provides reusable ABNF core and grammar-syntax rules from RFC 2234. Builds named rules
 * through the shared authoring API and serves as the base for later ABNF ruleset revisions.
 *
 * @see Rfc4234 Supersedes this RFC.
 * @see https://www.rfc-editor.org/rfc/rfc2234
 * @link https://www.rfc-editor.org/rfc/rfc2234.txt
 */
class Rfc2234 extends AbstractRuleset
{
    /**
     * @var string
     */
    protected static $name = 'Rfc2234';

    /**
     * @param array<string, C|Parser|Definition|callable> $overwrites
     */
    public function __construct($overwrites = [])
    {
        /*
         * Support rules.
         * These are not part of the published specification, but help make the published rules more manageable without
         * altering meaning or syntax. They exist outside the named scope.
         */
        $hexdigs = $this->rule('HEXDIG')->some();
        $digits = $this->rule('DIGIT')->some();
        $bits = $this->rule('BIT')->some();
        $c_wsps = $this->rule('c_wsp')->many();
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
                '<',
                C::choice(C::range(chr(0x20), chr(0x3d)), C::range(chr(0x3f), chr(0x7e)))->many(),
                '>',
            ),
            'hex_val' => C::sequence(
                'x',
                $hexdigs,
                C::choice(
                    C::sequence('.', $hexdigs)->some(),
                    C::sequence('-', $hexdigs),
                )->maybe(),
            ),
            'dec_val' => C::sequence(
                'd',
                $digits,
                C::choice(
                    C::sequence('.', $digits)->some(),
                    C::sequence('-', $digits),
                )->maybe(),
            ),
            'bin_val' => C::sequence(
                'b',
                $bits,
                C::choice(
                    C::sequence('.', $bits)->some(),
                    C::sequence('-', $bits),
                )->maybe(),
            ),
            'num_val' => C::sequence(
                '%',
                C::choice($this->rule('bin_val'), $this->rule('dec_val'), $this->rule('hex_val')),
            ),
            'char_val' => C::sequence(
                $this->rule('DQUOTE'),
                C::choice(C::range(chr(0x20), chr(0x21)), C::range(chr(0x23), chr(0x7e)))->many(),
                $this->rule('DQUOTE'),
            ),
            'option' => C::sequence(
                '[',
                $c_wsps,
                $this->rule('alternation'),
                $c_wsps,
                ']',
            ),
            'group' => C::sequence(
                '(',
                $c_wsps,
                $this->rule('alternation'),
                $c_wsps,
                ')',
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
                    $this->rule('DIGIT')->many(),
                    '*',
                    $this->rule('DIGIT')->many(),
                ),
            ),
            'repetition' => C::sequence(
                $this->rule('repeat')->maybe(),
                $this->rule('element'),
            ),
            'concatenation' => C::sequence(
                $this->rule('repetition'),
                C::sequence($this->rule('c_wsp')->some(), $this->rule('repetition'))->many(),
            ),
            'alternation' => C::sequence(
                $this->rule('concatenation'),
                C::sequence($c_wsps, '/', $c_wsps, $this->rule('concatenation'))->many(),
            ),
            'comment' => C::sequence(
                ';',
                C::choice($this->rule('WSP'), $this->rule('VCHAR'))->many(),
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
                C::choice('=', '=/'),
                $c_wsps,
            ),
            'rulename' => C::sequence(
                $this->rule('ALPHA'),
                C::choice($this->rule('ALPHA'), $this->rule('DIGIT'), '-')->many(),
            ),
            'rule' => C::sequence(
                $this->rule('rulename'),
                $this->rule('defined_as'),
                $this->rule('elements'),
                $this->rule('c_nl'),
            ),
            'rulelist' => C::choice(
                $this->rule('rule'),
                C::sequence($c_wsps, $this->rule('c_nl')),
            )->some(),
            self::ROOT => $this->rule('rulelist'),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
