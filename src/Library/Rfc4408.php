<?php

/** @noinspection PhpUndefinedFieldInspection */

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
 * @property-read Parser record  Complete SPF record
 * @property-read Parser version SPF version tag
 * @property-read Parser qnum    Integer value between 0 and 255
 * @property-read Parser IP4     IPv4 address with optional CIDR range
 * @property-read Parser IP6     IPv6 address with optional CIDR range
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
            'record'           => [$this->version, $this->terms, $this->SP],
            'version'          => 'v=spf1',

            // 4.6.1. Term Evaluation
            'terms'            => star([plus($abnf->SP), c($this->directive, $this->modifier)]),
            'directive'        => [opt($this->qualifier), $this->mechanism],
            'qualifier'        => set('+-?~'),
            'mechanism'        => c($this->all, $this->include, $this->A, $this->MX, $this->PTR, $this->IP4, $this->IP6,
                $this->exists), // @todo order?
            'modifier'         => c($this->redirect, $this->explanation, $this->unknown_modifier),
            'unknown_modifier' => [$this->name, '=', $this->macro_string],
            'name'             => [$abnf->ALPHA, star(c($abnf->ALPHA, $abnf->DIGIT, '-', '_', '.'))],

            // 5.1. "all"
            'all'              => 'all',

            // 5.2. "include"
            'include'          => ['include', ':', $this->domain_spec],

            // 5.3. "a"
            'A'                => ['a', opt(s(':', $this->domain_spec)), opt($this->dual_cidr_length)],

            // 5.4. "mx"
            'MX'               => ['mx', opt(s(':', $this->domain_spec)), opt($this->dual_cidr_length)],

            // 5.5. "ptr"
            'PTR'              => ['ptr', ':', $this->domain_spec],

            // 5.6. "ip4" and "ip6"
            'IP4'              => ['ip4', ':', $this->ip4_network, opt($this->ip4_cidr_length)],
            'IP6'              => ['ip6', ':', $this->ip6_network, opt($this->ip6_cidr_length)],
            'ip4_cidr_length'  => ['/', plus($abnf->DIGIT)],
            'ip6_cidr_length'  => ['/', plus($abnf->DIGIT)],
            'dual_cidr_length' => [opt($this->ip4_cidr_length), opt(['/', $this->ip6_cidr_length])],
            'ip4_network'      => s($this->qnum, '.', $this->qnum, '.', $this->qnum, '.', $this->qnum),
            'qnum'             => new Integer(0, 255),
            'ip6_network'      => $ipv6->ipv6_address,

            // 5.7. "exists"
            'exists'           => ['exists', ':', $this->domain_spec],

            // 6.1. redirect: Redirected Query
            'redirect'         => ['redirect', '=', $this->domain_spec],

            // 6.2. exp: Explanation
            'explanation'      => ['exp', '=', $this->domain_spec],

            // 8. Macros
            'domain_spec'      => [$this->macro_string, $this->domain_end],
            'domain_end'       => c(['.', $this->toplabel, opt('.')], $this->macro_expand),
            'toplabel'         => c(
                [star($this->alphanum), $abnf->ALPHA, star($this->alphanum)],
                [plus($this->alphanum), '=', star(c($this->alphanum, '-')), $this->alphanum]
            ), // LDH rule plus additional TLD restrictions (see [RFC3696], Section 2) @todo Read & implement
            'alphanum'         => c($abnf->ALPHA, $abnf->DIGIT),
            'explain_string'   => star(c($this->macro_string, $abnf->SP)),
            'macro_string'     => star(c($this->macro_expand, $this->macro_literal)),
            'macro_expand'     => c(
                ['%{', $this->macro_letter, $this->transformers, star($this->delimiter), '}'],
                '%%', '%_', '%-'
            ),
            'macro_literal'    => c(range(0x21, 0x24), range(0x26, 0x7E)),
            'macro_letter'     => set('slodiphcrt'),
            'transformers'     => [star($abnf->DIGIT), opt('r')],
            'delimiter'        => set('.-+,/_='),

            // 7. The Received-SPF Header Field
            // Implement as separate ruleset?
            // Does it conflict with the record definition?

            self::ROOT => $this->record,
        ];

        parent::__construct(array_merge($rules, $overwrites));
    }
}
