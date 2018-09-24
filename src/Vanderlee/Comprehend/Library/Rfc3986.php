<?php
/** @noinspection PhpUndefinedFieldInspection */

/**
 * RFC 3986 - Uniform Resource Identifier (URI): Generic Syntax
 *
 * Updated by RFC 3986
 *
 * @see https://tools.ietf.org/html/rfc3986
 * @package vanderlee\comprehend\library
 */

namespace vanderlee\comprehend\library;

use vanderlee\comprehend\builder\AbstractRuleset;
use vanderlee\comprehend\parser\Parser;
use vanderlee\comprehend\parser\terminal\Nothing;

require_once 'functions.php';

/**
 * Class Rfc3986
 *
 * @property-read Parser IPv6address
 * @property-read Parser IPv4address
 * @property-read Parser sub_delims
 *
 * @package vanderlee\comprehend\library
 */
class Rfc3986 extends AbstractRuleset
{
    protected static $name = 'Rfc3986';

    public function __construct($overwrites = [])
    {
        /** @var Rfc2234 $abnf */
        $abnf = Library::abnf();

        // Defined out-of-order for performance reasons
        $rules = [
            // 2.1.  Percent-Encoding
            'pct_encoded'   => s("%", $abnf->HEXDIG, $abnf->HEXDIG),

            // 2.2.  Reserved Characters
            'sub_delims'    => set('!$&\'()*+,;='),
            'gen_delims'    => set(':/?#[]@'),
            'reserved'      => c($this->gen_delims, $this->sub_delims),

            // 2.3.  Unreserved Characters
            'unreserved'    => c($abnf->ALPHA, $abnf->DIGIT, set('-._~')),

            // 3.3.  Path
            'pchar'         => c($this->unreserved, $this->pct_encoded, $this->sub_delims, ':', '@'),
            'segment'       => star($this->pchar),
            'segment_nz'    => plus($this->pchar),
            'segment_nz_nc' => plus(c($this->unreserved, $this->pct_encoded, $this->sub_delims, '@')),
            'path_abempty'  => star(['/', $this->segment]),
            'path_absolute' => s('/', opt([$this->segment_nz, star(['/', $this->segment])])),
            'path_noscheme' => s($this->segment_nz_nc, star(['/', $this->segment])),
            'path_rootless' => s($this->segment_nz, star(['/', $this->segment])),
            'path_empty'    => new Nothing(),
            'path'          => c($this->path_abempty,
                $this->path_absolute,
                $this->path_noscheme,
                $this->path_rootless,
                $this->path_empty
            ),

            // 3.4.  Query
            'query'         => star(c($this->pchar, '/', '?')),

            // 3.5.  Fragment
            'fragment    '  => star(c($this->pchar, '/', '?')),

            // 3.  Syntax Components
            'hier_part'     => s('//', $this->authority, [
                $this->path_abempty,
                $this->path_absolute,
                $this->path_rootless,
                $this->path_empty,
            ]),
            'URI'           => s($this->scheme, ':', $this->hier_part, opt(['?', $this->query]), opt(['#', $this->fragment])),

            // 3.1.  Scheme
            'scheme'        => s($abnf->ALPHA, star(c($abnf->ALPHA, $abnf->DIGIT, '+', '-', '.'))),

            // 3.2.  Authority
            'authority'     => s(opt([$this->userinfo, '@']), $this->host, opt([':', $this->port])),

            // 3.2.1.  User Information
            'userinfo'      => star(c($this->unreserved, $this->pct_encoded, $this->sub_delims, ':')),

            // 3.2.3.  Port
            'port'          => star($abnf->DIGIT),

            // 3.2.2.  Host
            'h16'           => repeat(1, 4, $abnf->HEXDIG()),
            'ls32'          => c([$this->h16, ':', $this->h16], $this->IPv4address),
            'IPv6address'   => c(
                [repeat(6, 6, [$this->h16, ':']), $this->ls32],
                ['::', repeat(5, 5, [$this->h16, ':']), $this->ls32],
                [opt($this->h16), '::', repeat(4, 4, [$this->h16, ':']), $this->ls32],
                [opt([repeat(0, 1, [$this->h16, ':',]), $this->h16]), '::', repeat(3, 3, [$this->h16, ':']), $this->ls32],
                [opt([repeat(0, 2, [$this->h16, ':',]), $this->h16]), '::', repeat(2, 2, [$this->h16, ':']), $this->ls32],
                [opt([repeat(0, 3, [$this->h16, ':',]), $this->h16]), '::', $this->h16, ':', $this->ls32],
                [opt([repeat(0, 4, [$this->h16, ':',]), $this->h16]), '::', $this->ls32],
                [opt([repeat(0, 5, [$this->h16, ':',]), $this->h16]), '::', $this->h16],
                [opt([repeat(0, 6, [$this->h16, ':',]), $this->h16]), '::']
            ),
            'dec_octet'     => c(
                ['25', range('0', '5')], // 250-255
                ['2', range('0', '4'), $abnf->DIGIT], // 200-249
                ['1', repeat(2, 2, $abnf->DIGIT)], // 100-199
                [range('1', '9'), $abnf->DIGIT], // 10-99
                $abnf->DIGIT   // 0-9
            ),
            'IPv4address'   => s($this->dec_octet, '.', $this->dec_octet, '.', $this->dec_octet, '.', $this->dec_octet),
            'IPvFuture'     => s('v', plus($abnf->HEXDIG), '.', plus(c($this->unreserved, $this->sub_delims, ':'))),
            'IP_literal'    => s('[', [$this->IPv6address, $this->IPvFuture], ']'),
            'reg_name'      => star(c($this->unreserved, $this->pct_encoded, $this->sub_delims)),
            'host'          => c($this->IP_literal, $this->IPv4address, $this->reg_name),

            // 4.2.  Relative Reference
            'relative_part' => s('//', $this->authority, [
                $this->path_abempty,
                $this->path_absolute,
                $this->path_noscheme,
                $this->path_empty
            ]),
            'relative_ref'  => s($this->relative_part, opt(['?', $this->query]), opt(['#', $this->fragment])),

            // 4.1.  URI Reference
            'URI_reference' => c($this->URI, $this->relative_ref),

            // 4.3.  Absolute URI
            'absolute_URI'  => s($this->scheme, ':', $this->hier_part, opt(['?', $this->query])),

            //            self::DEFAULT => $this->rulelist,
        ];

        parent::__construct(array_merge($rules, $overwrites));
    }
}