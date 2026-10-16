<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Uniform Resource Identifier (URI): Generic Syntax.
 *
 * URIs (web addresses and other resource identifiers).
 *
 * Provides named URI components and complete URI rules from RFC 3986. Composes reusable
 * terminals and address rules for applications that need structured URI recognition.
 *
 * @see https://www.rfc-editor.org/rfc/rfc3986
 * @link https://www.rfc-editor.org/rfc/rfc3986.txt
 */
class Rfc3986 extends AbstractRuleset
{
    /**
     * @var string
     */
    protected static $name = 'Rfc3986';

    /**
     * @param array<string, C|Parser|Definition|callable> $overwrites
     */
    public function __construct($overwrites = [])
    {
        $abnf = new Rfc2234();
        // A left-prefix hextet must not consume the first colon of the :: separator.
        $h16Colon = C::sequence($this->rule('h16'), C::not(C::text('::')), C::text(':'));
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
            /* Original combinator rule (RFC syntax documentation):
            'unreserved' => C::choice($abnf->rule('ALPHA'), $abnf->rule('DIGIT'), C::set('-._~')),
            */
            'unreserved' => C::set('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~'),
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
            /* Original combinator rule (RFC syntax documentation):
            'path' => C::choice(
                $this->rule('path_abempty'),
                $this->rule('path_absolute'),
                $this->rule('path_noscheme'),
                $this->rule('path_rootless'),
                $this->rule('path_empty'),
            ),
            */
            // A nullable first alternative must not hide a nonempty rootless path.
            'path' => C::longest(
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
            'hier_part' => C::choice(
                C::sequence(
                    C::text('//'),
                    $this->rule('authority'),
                    $this->rule('path_abempty'),
                ),
                $this->rule('path_absolute'),
                $this->rule('path_rootless'),
                $this->rule('path_empty'),
            ),
            'URI' => C::sequence(
                $this->rule('scheme'),
                C::text(':'),
                $this->rule('hier_part'),
                C::sequence(C::text('?'), $this->rule('query'))->maybe(),
                C::sequence(C::text('#'), $this->rule('fragment'))->maybe(),
            ),
            // 3.1.  Scheme
            /* Original combinator rule (RFC syntax documentation):
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
            */
            'scheme' => C::regex('/[A-Za-z][A-Za-z0-9+.-]*/A'),
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
            /* Original combinator rule (RFC syntax documentation):
            'h16' => $abnf->rule('HEXDIG')->repeat(1, 4),
            */
            'h16' => C::regex('/[0-9A-Fa-f]{1,4}/A'),
            'ls32' => C::choice(
                C::sequence($this->rule('h16'), C::text(':'), $this->rule('h16')),
                $this->rule('IPv4address'),
            ),
            /* Original combinator rule (RFC syntax documentation):
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
            */
            'IPv6address' => C::choice(
                C::sequence(
                    $h16Colon->repeat(6, 6),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::text('::'),
                    $h16Colon->repeat(5, 5),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    $this->rule('h16')->maybe(),
                    C::text('::'),
                    $h16Colon->repeat(4, 4),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        $h16Colon->maybe(),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $h16Colon->repeat(3, 3),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        $h16Colon->repeat(0, 2),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $h16Colon->repeat(2, 2),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        $h16Colon->repeat(0, 3),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $this->rule('h16'),
                    C::text(':'),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        $h16Colon->repeat(0, 4),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $this->rule('ls32'),
                ),
                C::sequence(
                    C::sequence(
                        $h16Colon->repeat(0, 5),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                    $this->rule('h16'),
                ),
                C::sequence(
                    C::sequence(
                        $h16Colon->repeat(0, 6),
                        $this->rule('h16'),
                    )->maybe(),
                    C::text('::'),
                ),
            ),
            /* Original combinator rule (RFC syntax documentation):
            'dec_octet' => C::choice(
                C::sequence(C::text('25'), C::range('0', '5')),
                C::sequence(C::text('2'), C::range('0', '4'), $abnf->rule('DIGIT')),
                C::sequence(C::text('1'), $abnf->rule('DIGIT')->repeat(2, 2)),
                C::sequence(C::range('1', '9'), $abnf->rule('DIGIT')),
                $abnf->rule('DIGIT'),
            ),
            */
            'dec_octet' => C::regex('/(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9])/A'),
            'IPv4address' => C::sequence(
                $this->rule('dec_octet'),
                C::text('.'),
                $this->rule('dec_octet'),
                C::text('.'),
                $this->rule('dec_octet'),
                C::text('.'),
                $this->rule('dec_octet'),
            ),
            /* Original combinator rule (RFC syntax documentation):
            'IPvFuture' => C::sequence(
                C::text('v'),
                $abnf->rule('HEXDIG')->some(),
                C::text('.'),
                C::choice($this->rule('unreserved'), $this->rule('sub_delims'), C::text(':'))->some(),
            ),
            */
            'IPvFuture' => C::sequence(
                C::text('v')->caseSensitive(false),
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
            /* Original combinator rule (RFC syntax documentation):
            'host' => C::choice(
                $this->rule('IP_literal'),
                $this->rule('IPv4address'),
                $this->rule('reg_name'),
            ),
            */
            'host' => C::choice(
                $this->rule('IP_literal'),
                // An IPv4-looking prefix is a reg-name when more host characters follow.
                C::sequence(
                    $this->rule('IPv4address'),
                    C::not(C::choice(
                        $this->rule('unreserved'),
                        $this->rule('pct_encoded'),
                        $this->rule('sub_delims'),
                    )),
                ),
                $this->rule('reg_name'),
            ),
            // 4.2.  Relative Reference
            'relative_part' => C::choice(
                C::sequence(
                    C::text('//'),
                    $this->rule('authority'),
                    $this->rule('path_abempty'),
                ),
                $this->rule('path_absolute'),
                $this->rule('path_noscheme'),
                $this->rule('path_empty'),
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
