<?php

declare (strict_types=1);
/**
 * RFC 3986 - Uniform Resource Identifier (URI): Generic Syntax.
 *
 * @see https://tools.ietf.org/html/rfc3986
 */

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;

require_once 'functions.php';

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
            'pct_encoded' => s(text('%'), $abnf->rule('HEXDIG'), $abnf->rule('HEXDIG')),
            // 2.2.  Reserved Characters
            'sub_delims' => set('!$&\'()*+,;='),
            'gen_delims' => set(':/?#[]@'),
            'reserved' => c($this->rule('gen_delims'), $this->rule('sub_delims')),
            // 2.3.  Unreserved Characters
            'unreserved' => c($abnf->rule('ALPHA'), $abnf->rule('DIGIT'), set('-._~')),
            // 3.3.  Path
            'pchar' => c(
                $this->rule('unreserved'),
                $this->rule('pct_encoded'),
                $this->rule('sub_delims'),
                text(':'),
                text('@'),
            ),
            'segment' => star($this->rule('pchar')),
            'segment_nz' => plus($this->rule('pchar')),
            'segment_nz_nc' => plus(
                c($this->rule('unreserved'), $this->rule('pct_encoded'), $this->rule('sub_delims'), text('@'))
            ),
            'path_abempty' => star(s(text('/'), $this->rule('segment'))),
            'path_absolute' => s(
                text('/'),
                opt(s($this->rule('segment_nz'), star(s(text('/'), $this->rule('segment')))))
            ),
            'path_noscheme' => s($this->rule('segment_nz_nc'), star(s(text('/'), $this->rule('segment')))),
            'path_rootless' => s($this->rule('segment_nz'), star(s(text('/'), $this->rule('segment')))),
            'path_empty' => new Nothing(),
            'path' => c(
                $this->rule('path_abempty'),
                $this->rule('path_absolute'),
                $this->rule('path_noscheme'),
                $this->rule('path_rootless'),
                $this->rule('path_empty'),
            ),
            // 3.4.  Query
            'query' => star(c($this->rule('pchar'), text('/'), text('?'))),
            // 3.5.  Fragment
            'fragment' => star(c($this->rule('pchar'), text('/'), text('?'))),
            // 3.  Syntax Components
            'hier_part' => s(
                text('//'),
                $this->rule('authority'),
                c(
                    $this->rule('path_abempty'),
                    $this->rule('path_absolute'),
                    $this->rule('path_rootless'),
                    $this->rule('path_empty'),
                ),
            ),
            'URI' => s(
                $this->rule('scheme'),
                text(':'),
                $this->rule('hier_part'),
                opt(s(text('?'), $this->rule('query'))),
                opt(s(text('#'), $this->rule('fragment'))),
            ),
            // 3.1.  Scheme
            'scheme' => s(
                $abnf->rule('ALPHA'),
                star(c($abnf->rule('ALPHA'), $abnf->rule('DIGIT'), text('+'), text('-'), text('.'))),
            ),
            // 3.2.  Authority
            'authority' => s(
                opt(s($this->rule('userinfo'), text('@'))),
                $this->rule('host'),
                opt(s(text(':'), $this->rule('port'))),
            ),
            // 3.2.1.  User Information
            'userinfo' => star(
                c($this->rule('unreserved'), $this->rule('pct_encoded'), $this->rule('sub_delims'), text(':'))
            ),
            // 3.2.3.  Port
            'port' => star($abnf->rule('DIGIT')),
            // 3.2.2.  Host
            'h16' => repeat(1, 4, $abnf->rule('HEXDIG')),
            'ls32' => c(s($this->rule('h16'), text(':'), $this->rule('h16')), $this->rule('IPv4address')),
            'IPv6address' => c(
                s(repeat(6, 6, s($this->rule('h16'), text(':'))), $this->rule('ls32')),
                s(text('::'), repeat(5, 5, s($this->rule('h16'), text(':'))), $this->rule('ls32')),
                s(
                    opt($this->rule('h16')),
                    text('::'),
                    repeat(4, 4, s($this->rule('h16'), text(':'))),
                    $this->rule('ls32'),
                ),
                s(
                    opt(s(repeat(0, 1, s($this->rule('h16'), text(':'))), $this->rule('h16'))),
                    text('::'),
                    repeat(3, 3, s($this->rule('h16'), text(':'))),
                    $this->rule('ls32'),
                ),
                s(
                    opt(s(repeat(0, 2, s($this->rule('h16'), text(':'))), $this->rule('h16'))),
                    text('::'),
                    repeat(2, 2, s($this->rule('h16'), text(':'))),
                    $this->rule('ls32'),
                ),
                s(
                    opt(s(repeat(0, 3, s($this->rule('h16'), text(':'))), $this->rule('h16'))),
                    text('::'),
                    $this->rule('h16'),
                    text(':'),
                    $this->rule('ls32'),
                ),
                s(
                    opt(s(repeat(0, 4, s($this->rule('h16'), text(':'))), $this->rule('h16'))),
                    text('::'),
                    $this->rule('ls32'),
                ),
                s(
                    opt(s(repeat(0, 5, s($this->rule('h16'), text(':'))), $this->rule('h16'))),
                    text('::'),
                    $this->rule('h16'),
                ),
                s(opt(s(repeat(0, 6, s($this->rule('h16'), text(':'))), $this->rule('h16'))), text('::')),
            ),
            'dec_octet' => c(
                s(text('25'), range('0', '5')),
                s(text('2'), range('0', '4'), $abnf->rule('DIGIT')),
                s(text('1'), repeat(2, 2, $abnf->rule('DIGIT'))),
                s(range('1', '9'), $abnf->rule('DIGIT')),
                $abnf->rule('DIGIT'),
            ),
            'IPv4address' => s(
                $this->rule('dec_octet'),
                text('.'),
                $this->rule('dec_octet'),
                text('.'),
                $this->rule('dec_octet'),
                text('.'),
                $this->rule('dec_octet'),
            ),
            'IPvFuture' => s(
                text('v'),
                plus($abnf->rule('HEXDIG')),
                text('.'),
                plus(c($this->rule('unreserved'), $this->rule('sub_delims'), text(':'))),
            ),
            'IP_literal' => s(text('['), c($this->rule('IPv6address'), $this->rule('IPvFuture')), text(']')),
            'reg_name' => star(c($this->rule('unreserved'), $this->rule('pct_encoded'), $this->rule('sub_delims'))),
            'host' => c($this->rule('IP_literal'), $this->rule('IPv4address'), $this->rule('reg_name')),
            // 4.2.  Relative Reference
            'relative_part' => s(
                text('//'),
                $this->rule('authority'),
                c(
                    $this->rule('path_abempty'),
                    $this->rule('path_absolute'),
                    $this->rule('path_noscheme'),
                    $this->rule('path_empty'),
                ),
            ),
            'relative_ref' => s(
                $this->rule('relative_part'),
                opt(s(text('?'), $this->rule('query'))),
                opt(s(text('#'), $this->rule('fragment'))),
            ),
            // 4.1.  URI Reference
            'URI_reference' => c($this->rule('URI'), $this->rule('relative_ref')),
            // 4.3.  Absolute URI
            'absolute_URI' => s(
                $this->rule('scheme'),
                text(':'),
                $this->rule('hier_part'),
                opt(s(text('?'), $this->rule('query')))
            ),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
