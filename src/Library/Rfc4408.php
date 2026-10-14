<?php

declare (strict_types=1);
/**
 * RFC 4408 - Sender Policy Framework (SPF) for Authorizing Use of Domains in E-Mail, Version 1.
 *
 * Updated by RFC 6652
 * Obsoleted by RFC 7208
 *
 * @see     https://tools.ietf.org/html/rfc4408
 */
namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Integer;
require_once 'functions.php';
/**
 * Class Rfc4408.
 *
 * ABNF in official RFC specs does not take into account left hand recursion issues.
 * Instances are fixed manually where appropriate.
 *
 */
class Rfc4408 extends AbstractRuleset
{
    protected static $name = 'Rfc4408';
    public function __construct($overwrites = [])
    {
        $abnf = new Rfc4234();
        $ipv6 = new Rfc3513();
        /*
         * Normal rules
         */
        $rules = [
            // 4.5. Selecting Records
            'record' => s($this->rule('version'), $this->rule('terms'), $this->rule('SP')),
            'version' => $this->rule('v=spf1'),
            // 4.6.1. Term Evaluation
            'terms' => star(s(plus($abnf->rule('SP')), c($this->rule('directive'), $this->rule('modifier')))),
            'directive' => s(opt($this->rule('qualifier')), $this->rule('mechanism')),
            'qualifier' => set('+-?~'),
            'mechanism' => c(
                $this->rule('all'),
                $this->rule('include'),
                $this->rule('A'),
                $this->rule('MX'),
                $this->rule('PTR'),
                $this->rule('IP4'),
                $this->rule('IP6'),
                $this->rule('exists'),
            ),
            // @todo order?
            'modifier' => c($this->rule('redirect'), $this->rule('explanation'), $this->rule('unknown_modifier')),
            'unknown_modifier' => s($this->rule('name'), text('='), $this->rule('macro_string')),
            'name' => s(
                $abnf->rule('ALPHA'),
                star(c($abnf->rule('ALPHA'), $abnf->rule('DIGIT'), text('-'), text('_'), text('.'))),
            ),
            // 5.1. "all"
            'all' => $this->rule('all'),
            // 5.2. "include"
            'include' => s(text('include'), text(':'), $this->rule('domain_spec')),
            // 5.3. "a"
            'A' => s(text('a'), opt(s(text(':'), $this->rule('domain_spec'))), opt($this->rule('dual_cidr_length'))),
            // 5.4. "mx"
            'MX' => s(text('mx'), opt(s(text(':'), $this->rule('domain_spec'))), opt($this->rule('dual_cidr_length'))),
            // 5.5. "ptr"
            'PTR' => s(text('ptr'), text(':'), $this->rule('domain_spec')),
            // 5.6. "ip4" and "ip6"
            'IP4' => s(text('ip4'), text(':'), $this->rule('ip4_network'), opt($this->rule('ip4_cidr_length'))),
            'IP6' => s(text('ip6'), text(':'), $this->rule('ip6_network'), opt($this->rule('ip6_cidr_length'))),
            'ip4_cidr_length' => s(text('/'), plus($abnf->rule('DIGIT'))),
            'ip6_cidr_length' => s(text('/'), plus($abnf->rule('DIGIT'))),
            'dual_cidr_length' => s(opt($this->rule('ip4_cidr_length')), opt(s(text('/'), $this->rule('ip6_cidr_length')))),
            'ip4_network' => s(
                $this->rule('qnum'),
                text('.'),
                $this->rule('qnum'),
                text('.'),
                $this->rule('qnum'),
                text('.'),
                $this->rule('qnum'),
            ),
            'qnum' => new Integer(0, 255),
            'ip6_network' => $ipv6->rule('ipv6_address'),
            // 5.7. "exists"
            'exists' => s(text('exists'), text(':'), $this->rule('domain_spec')),
            // 6.1. redirect: Redirected Query
            'redirect' => s(text('redirect'), text('='), $this->rule('domain_spec')),
            // 6.2. exp: Explanation
            'explanation' => s(text('exp'), text('='), $this->rule('domain_spec')),
            // 8. Macros
            'domain_spec' => s($this->rule('macro_string'), $this->rule('domain_end')),
            'domain_end' => c(s(text('.'), $this->rule('toplabel'), opt(text('.'))), $this->rule('macro_expand')),
            'toplabel' => c(
                s(star($this->rule('alphanum')), $abnf->rule('ALPHA'), star($this->rule('alphanum'))),
                s(
                    plus($this->rule('alphanum')),
                    text('='),
                    star(c($this->rule('alphanum'), text('-'))),
                    $this->rule('alphanum'),
                ),
            ),
            // LDH rule plus additional TLD restrictions (see [RFC3696], Section 2) @todo Read & implement
            'alphanum' => c($abnf->rule('ALPHA'), $abnf->rule('DIGIT')),
            'explain_string' => star(c($this->rule('macro_string'), $abnf->rule('SP'))),
            'macro_string' => star(c($this->rule('macro_expand'), $this->rule('macro_literal'))),
            'macro_expand' => c(
                s(
                    text('%{'),
                    $this->rule('macro_letter'),
                    $this->rule('transformers'),
                    star($this->rule('delimiter')),
                    text('}'),
                ),
                text('%%'),
                text('%_'),
                text('%-'),
            ),
            'macro_literal' => c(range(chr(0x21), chr(0x24)), range(chr(0x26), chr(0x7e))),
            'macro_letter' => set('slodiphcrt'),
            'transformers' => s(star($abnf->rule('DIGIT')), opt(text('r'))),
            'delimiter' => set('.-+,/_='),
            // 7. The Received-SPF Header Field
            // Implement as separate ruleset?
            // Does it conflict with the record definition?
            self::ROOT => $this->rule('record'),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
