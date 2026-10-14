<?php

declare (strict_types=1);
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
        $hexdigs = plus($this->rule('HEXDIG'));
        $digits = plus($this->rule('DIGIT'));
        $bits = plus($this->rule('BIT'));
        $c_wsps = star($this->rule('c_wsp'));
        /*
         * Normal rules
         */
        $rules = [
            /*
             * Core rules
             */
            'ALPHA' => set('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'),
            'BIT' => set('01'),
            'CHAR' => range(chr(0x1), chr(0x7f)),
            'CR' => char("\r"),
            'CRLF' => text("\r\n"),
            'DIGIT' => set('0123456789'),
            'DQUOTE' => char('"'),
            'HEXDIG' => set('0123456789ABCDEF'),
            'HTAB' => char("\t"),
            'LF' => char("\n"),
            'LWSP' => regex("/(?:[ \t]|(?:\r\n[ \t]))*/"),
            'OCTET' => range(chr(0x0), chr(0xff)),
            'SP' => char(' '),
            'VCHAR' => range(chr(0x21), chr(0x7e)),
            // ['!', '~']
            'WSP' => set(" \t"),
            /*
             * Definition of ABNF syntax (in reverse order for performance)
             */
            'prose_val' => s(text('<'), star(c(range(chr(0x20), chr(0x3d)), range(chr(0x3f), chr(0x7e)))), text('>')),
            'hex_val' => s(text('x'), $hexdigs, opt(c(plus(s(text('.'), $hexdigs)), s(text('-'), $hexdigs)))),
            'dec_val' => s(text('d'), $digits, opt(c(plus(s(text('.'), $digits)), s(text('-'), $digits)))),
            'bin_val' => s(text('b'), $bits, opt(c(plus(s(text('.'), $bits)), s(text('-'), $bits)))),
            'num_val' => s(text('%'), c($this->rule('bin_val'), $this->rule('dec_val'), $this->rule('hex_val'))),
            'char_val' => s(
                $this->rule('DQUOTE'),
                star(c(range(chr(0x20), chr(0x21)), range(chr(0x23), chr(0x7e)))),
                $this->rule('DQUOTE'),
            ),
            'option' => s(text('['), $c_wsps, $this->rule('alternation'), $c_wsps, text(']')),
            'group' => s(text('('), $c_wsps, $this->rule('alternation'), $c_wsps, text(')')),
            'element' => c(
                $this->rule('rulename'),
                $this->rule('group'),
                $this->rule('option'),
                $this->rule('char_val'),
                $this->rule('num_val'),
                $this->rule('prose_val'),
            ),
            'repeat' => c($digits, s(star($this->rule('DIGIT')), text('*'), star($this->rule('DIGIT')))),
            'repetition' => s(opt($this->rule('repeat')), $this->rule('element')),
            'concatenation' => s($this->rule('repetition'), star(s(plus($this->rule('c_wsp')), $this->rule('repetition')))),
            'alternation' => s($this->rule('concatenation'), star(s($c_wsps, text('/'), $c_wsps, $this->rule('concatenation')))),
            'comment' => s(text(';'), star(c($this->rule('WSP'), $this->rule('VCHAR'))), $this->rule('CRLF')),
            'c_nl' => c($this->rule('comment'), $this->rule('CRLF')),
            'c_wsp' => c($this->rule('WSP'), s($this->rule('c_nl'), $this->rule('WSP'))),
            'elements' => s($this->rule('alternation'), $c_wsps),
            'defined_as' => s($c_wsps, c(text('='), text('=/')), $c_wsps),
            'rulename' => s($this->rule('ALPHA'), star(c($this->rule('ALPHA'), $this->rule('DIGIT'), text('-')))),
            'rule' => s($this->rule('rulename'), $this->rule('defined_as'), $this->rule('elements'), $this->rule('c_nl')),
            'rulelist' => plus(c($this->rule('rule'), s($c_wsps, $this->rule('c_nl')))),
            self::ROOT => $this->rule('rulelist'),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
