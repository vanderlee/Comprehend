<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Mail\Grammar;

use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc4234;
use Vanderlee\Comprehend\Extensions\Network\Grammar\Rfc3513;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Sender Policy Framework (SPF) for Authorizing Use of Domains in E-Mail, Version 1.
 *
 * SPF email sender authorization.
 *
 * Updated by RFC 6652
 * Obsoleted by RFC 7208
 *
 * Provides named SPF record and related syntax rules from RFC 4408. Rewrites left-recursive
 * productions for the shared combinator runtime so they can be composed into application
 * grammars.
 *
 * @see Rfc7208 Supersedes this RFC.
 * @see https://www.rfc-editor.org/rfc/rfc4408
 * @link https://www.rfc-editor.org/rfc/rfc4408.txt
 */
class Rfc4408 extends AbstractRuleset
{
    /**
     * @var string
     */
    protected static $name = 'Rfc4408';

    /**
     * @param array<string, C|Parser|Definition|callable> $overwrites
     */
    public function __construct($overwrites = [])
    {
        $abnf = new Rfc4234();
        $ipv6 = new Rfc3513();
        /*
         * Normal rules
         */
        $rules = [
            // 4.5. Selecting Records
            'record' => C::sequence($this->rule('version'), $this->rule('terms'), $abnf->rule('SP')->many()),
            'version' => C::text('v=spf1'),
            // 4.6.1. Term Evaluation
            'terms' => C::sequence(
                $abnf->rule('SP')->some(),
                C::choice($this->rule('directive'), $this->rule('modifier')),
            )->many(),
            'directive' => C::sequence(
                $this->rule('qualifier')->maybe(),
                $this->rule('mechanism'),
            ),
            'qualifier' => C::set('+-?~'),
            'mechanism' => C::choice(
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
            'modifier' => C::choice(
                $this->rule('redirect'),
                $this->rule('explanation'),
                $this->rule('unknown_modifier'),
            ),
            'unknown_modifier' => C::sequence(
                $this->rule('name'),
                C::text('='),
                $this->rule('macro_string'),
            ),
            'name' => C::sequence(
                $abnf->rule('ALPHA'),
                C::choice(
                    $abnf->rule('ALPHA'),
                    $abnf->rule('DIGIT'),
                    C::text('-'),
                    C::text('_'),
                    C::text('.'),
                )->many(),
            ),
            // 5.1. "all"
            'all' => C::text('all'),
            // 5.2. "include"
            'include' => C::sequence(
                C::text('include'),
                C::text(':'),
                $this->rule('domain_spec'),
            ),
            // 5.3. "a"
            'A' => C::sequence(
                C::text('a'),
                C::sequence(C::text(':'), $this->rule('domain_spec'))->maybe(),
                $this->rule('dual_cidr_length'),
            ),
            // 5.4. "mx"
            'MX' => C::sequence(
                C::text('mx'),
                C::sequence(C::text(':'), $this->rule('domain_spec'))->maybe(),
                $this->rule('dual_cidr_length'),
            ),
            // 5.5. "ptr"
            'PTR' => C::sequence(C::text('ptr'), C::text(':'), $this->rule('domain_spec')),
            // 5.6. "ip4" and "ip6"
            'IP4' => C::sequence(
                C::text('ip4'),
                C::text(':'),
                $this->rule('ip4_network'),
                $this->rule('ip4_cidr_length')->maybe(),
            ),
            'IP6' => C::sequence(
                C::text('ip6'),
                C::text(':'),
                $this->rule('ip6_network'),
                $this->rule('ip6_cidr_length')->maybe(),
            ),
            'ip4_cidr_length' => C::sequence(
                C::text('/'),
                $abnf->rule('DIGIT')->some(),
            ),
            'ip6_cidr_length' => C::sequence(
                C::text('/'),
                $abnf->rule('DIGIT')->some(),
            ),
            'dual_cidr_length' => C::sequence(
                $this->rule('ip4_cidr_length')->maybe(),
                C::sequence(C::text('/'), $this->rule('ip6_cidr_length'))->maybe(),
            ),
            'ip4_network' => C::sequence(
                $this->rule('qnum'),
                C::text('.'),
                $this->rule('qnum'),
                C::text('.'),
                $this->rule('qnum'),
                C::text('.'),
                $this->rule('qnum'),
            ),
            'qnum' => C::octet(),
            'ip6_network' => $ipv6->rule('ipv6_address'),
            // 5.7. "exists"
            'exists' => C::sequence(
                C::text('exists'),
                C::text(':'),
                $this->rule('domain_spec'),
            ),
            // 6.1. redirect: Redirected Query
            'redirect' => C::sequence(
                C::text('redirect'),
                C::text('='),
                $this->rule('domain_spec'),
            ),
            // 6.2. exp: Explanation
            'explanation' => C::sequence(
                C::text('exp'),
                C::text('='),
                $this->rule('domain_spec'),
            ),
            // 8. Macros
            'domain_spec' => C::sequence($this->rule('macro_string'), $this->rule('domain_end')),
            'domain_end' => C::choice(
                C::sequence(C::text('.'), $this->rule('toplabel'), C::text('.')->maybe()),
                $this->rule('macro_expand'),
            ),
            'toplabel' => C::choice(
                C::sequence(
                    $this->rule('alphanum')->many(),
                    $abnf->rule('ALPHA'),
                    $this->rule('alphanum')->many(),
                ),
                C::sequence(
                    $this->rule('alphanum')->some(),
                    C::text('='),
                    C::choice($this->rule('alphanum'), C::text('-'))->many(),
                    $this->rule('alphanum'),
                ),
            ),
            // LDH rule plus additional TLD restrictions (see [RFC3696], Section 2) @todo Read & implement
            'alphanum' => C::choice($abnf->rule('ALPHA'), $abnf->rule('DIGIT')),
            // Expand the nullable macro-string production so each repeated item consumes input.
            'explain_string' => C::choice(
                $this->rule('macro_expand'),
                $this->rule('macro_literal'),
                $abnf->rule('SP'),
            )->many(),
            'macro_string' => C::choice(
                $this->rule('macro_expand'),
                $this->rule('macro_literal'),
            )->many(),
            'macro_expand' => C::choice(
                C::sequence(
                    C::text('%{'),
                    $this->rule('macro_letter'),
                    $this->rule('transformers'),
                    $this->rule('delimiter')->many(),
                    C::text('}'),
                ),
                C::text('%%'),
                C::text('%_'),
                C::text('%-'),
            ),
            'macro_literal' => C::choice(
                C::range(chr(0x21), chr(0x24)),
                C::range(chr(0x26), chr(0x7e)),
            ),
            'macro_letter' => C::set('slodiphcrt'),
            'transformers' => C::sequence(
                $abnf->rule('DIGIT')->many(),
                C::text('r')->maybe(),
            ),
            'delimiter' => C::set('.-+,/_='),
            // 7. The Received-SPF Header Field
            // Implement as separate ruleset?
            // Does it conflict with the record definition?
            self::ROOT => $this->rule('record'),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
