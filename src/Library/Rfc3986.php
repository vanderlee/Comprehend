<?php

declare(strict_types=1);

/**
 * RFC 3986 - Uniform Resource Identifier (URI): Generic Syntax.
 *
 * @see https://tools.ietf.org/html/rfc3986
 */

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Comprehend as C;

/**
 */
class Rfc3986 extends AbstractRuleset
{
    protected static $name = 'Rfc3986';

    public function __construct($overwrites = [])
    {
        $abnf = new Rfc2234();
        // Defined out-of-order for performance reasons
        $rules = [
            // 2.1.  Percent-Encoding
            'pct_encoded' => C::sequence(
                C::text('%'),
                $abnf->rule('HEXDIG'),
                $abnf->rule('HEXDIG'),
            ),
            // 2.2.  Reserved Characters
            'sub_delims' => C::set('!$&\'()*+,;='),
            'gen_delims' => C::set(':/?#[]@'),
            'reserved' => C::choice($this->rule('gen_delims'), $this->rule('sub_delims')),
            // 2.3.  Unreserved Characters
            'unreserved' => C::choice($abnf->rule('ALPHA'), $abnf->rule('DIGIT'), C::set('-._~')),
            // 3.3.  Path
            'pchar' => C::choice(
                $this->rule('unreserved'),
                $this->rule('pct_encoded'),
                $this->rule('sub_delims'),
                C::text(':'),
                C::text('@'),
            ),
            'segment' => $this->rule('pchar')->many(),
            'segment_nz' => $this->rule('pchar')->some(),
            'segment_nz_nc' => C::choice(
                $this->rule('unreserved'),
                $this->rule('pct_encoded'),
                $this->rule('sub_delims'),
                C::text('@'),
            )->some(),
            'path_abempty' => C::sequence(C::text('/'), $this->rule('segment'))->many(),
            'path_absolute' => C::sequence(
                C::text('/'),
                C::sequence(
                    $this->rule('segment_nz'),
                    C::sequence(C::text('/'), $this->rule('segment'))->many(),
                )->maybe(),
            ),
            'path_noscheme' => C::sequence(
                $this->rule('segment_nz_nc'),
                C::sequence(C::text('/'), $this->rule('segment'))->many(),
            ),
            'path_rootless' => C::sequence(
                $this->rule('segment_nz'),
                C::sequence(C::text('/'), $this->rule('segment'))->many(),
            ),
            'path_empty' => C::nothing(),
            'path' => C::choice(
                $this->rule('path_abempty'),
                $this->rule('path_absolute'),
                $this->rule('path_noscheme'),
                $this->rule('path_rootless'),
                $this->rule('path_empty'),
            ),
            // 3.4.  Query
            'query' => C::choice(
                $this->rule('pchar'),
                C::text('/'),
                C::text('?'),
            )->many(),
            // 3.5.  Fragment
            'fragment' => C::choice(
                $this->rule('pchar'),
                C::text('/'),
                C::text('?'),
            )->many(),
            // 3.  Syntax Components
            'hier_part' => C::sequence(
                C::text('//'),
                $this->rule('authority'),
                C::choice(
                    $this->rule('path_abempty'),
                    $this->rule('path_absolute'),
                    $this->rule('path_rootless'),
                    $this->rule('path_empty'),
                ),
            ),
            'URI' => C::sequence(
                $this->rule('scheme'),
                C::text(':'),
                $this->rule('hier_part'),
                C::sequence(C::text('?'), $this->rule('query'))->maybe(),
                C::sequence(C::text('#'), $this->rule('fragment'))->maybe(),
            ),
            // 3.1.  Scheme
            'scheme' => C::sequence(
                $abnf->rule('ALPHA'),
                C::choice(
                    $abnf->rule('ALPHA'),
                    $abnf->rule('DIGIT'),
                    C::text('+'),
                    C::text('-'),
                    C::text('.'),
                )->many(),
            ),
            // 3.2.  Authority
            'authority' => C::sequence(
                C::sequence($this->rule('userinfo'), C::text('@'))->maybe(),
                $this->rule('host'),
                C::sequence(C::text(':'), $this->rule('port'))->maybe(),
            ),
            // 3.2.1.  User Information
            'userinfo' => C::choice(
                $this->rule('unreserved'),
                $this->rule('pct_encoded'),
                $this->rule('sub_delims'),
                C::text(':'),
            )->many(),
            // 3.2.3.  Port
            'port' => $abnf->rule('DIGIT')->many(),
            // 3.2.2.  Host
            'h16' => $abnf->rule('HEXDIG')->repeat(1, 4),
            'ls32' => C::choice(
                C::sequence($this->rule('h16'), C::text(':'), $this->rule('h16')),
                $this->rule('IPv4address'),
            ),
            'IPv6address' => C::choice(
                C::sequence(
                    C::sequence($this->rule('h16'), C::text(':'))->repeat(6, 6),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::text('::'),
                    C::sequence($this->rule('h16'), C::text(':'))->repeat(5, 5),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    $this->rule('h16')->maybe(),
                    C::text('::'),
                    C::sequence($this->rule('h16'), C::text(':'))->repeat(4, 4),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        C::sequence($this->rule('h16'), C::text(':'))->maybe(),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    C::sequence($this->rule('h16'), C::text(':'))->repeat(3, 3),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        C::sequence($this->rule('h16'), C::text(':'))->repeat(0, 2),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    C::sequence($this->rule('h16'), C::text(':'))->repeat(2, 2),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        C::sequence($this->rule('h16'), C::text(':'))->repeat(0, 3),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $this->rule('h16'),
                    C::text(':'),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        C::sequence($this->rule('h16'), C::text(':'))->repeat(0, 4),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        C::sequence($this->rule('h16'), C::text(':'))->repeat(0, 5),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $this->rule('h16'),
                ),
                C::sequence(
                    C::sequence(
                        C::sequence($this->rule('h16'), C::text(':'))->repeat(0, 6),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                ),
            ),
            'dec_octet' => C::choice(
                C::sequence(C::text('25'), C::range('0', '5')),
                C::sequence(C::text('2'), C::range('0', '4'), $abnf->rule('DIGIT')),
                C::sequence(C::text('1'), $abnf->rule('DIGIT')->repeat(2, 2)),
                C::sequence(C::range('1', '9'), $abnf->rule('DIGIT')),
                $abnf->rule('DIGIT'),
            ),
            'IPv4address' => C::sequence(
                $this->rule('dec_octet'),
                C::text('.'),
                $this->rule('dec_octet'),
                C::text('.'),
                $this->rule('dec_octet'),
                C::text('.'),
                $this->rule('dec_octet'),
            ),
            'IPvFuture' => C::sequence(
                C::text('v'),
                $abnf->rule('HEXDIG')->some(),
                C::text('.'),
                C::choice($this->rule('unreserved'), $this->rule('sub_delims'), C::text(':'))->some(),
            ),
            'IP_literal' => C::sequence(
                C::text('['),
                C::choice($this->rule('IPv6address'), $this->rule('IPvFuture')),
                C::text(']'),
            ),
            'reg_name' => C::choice(
                $this->rule('unreserved'),
                $this->rule('pct_encoded'),
                $this->rule('sub_delims'),
            )->many(),
            'host' => C::choice(
                $this->rule('IP_literal'),
                $this->rule('IPv4address'),
                $this->rule('reg_name'),
            ),
            // 4.2.  Relative Reference
            'relative_part' => C::sequence(
                C::text('//'),
                $this->rule('authority'),
                C::choice(
                    $this->rule('path_abempty'),
                    $this->rule('path_absolute'),
                    $this->rule('path_noscheme'),
                    $this->rule('path_empty'),
                ),
            ),
            'relative_ref' => C::sequence(
                $this->rule('relative_part'),
                C::sequence(C::text('?'), $this->rule('query'))->maybe(),
                C::sequence(C::text('#'), $this->rule('fragment'))->maybe(),
            ),
            // 4.1.  URI Reference
            'URI_reference' => C::choice($this->rule('URI'), $this->rule('relative_ref')),
            // 4.3.  Absolute URI
            'absolute_URI' => C::sequence(
                $this->rule('scheme'),
                C::text(':'),
                $this->rule('hier_part'),
                C::sequence(C::text('?'), $this->rule('query'))->maybe(),
            ),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
