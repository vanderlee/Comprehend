<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Sender Policy Framework (SPF) for Authorizing Use of Domains in Email, Version 1.
 *
 * SPF email sender authorization.
 *
 * Obsoletes RFC 4408
 *
 * Provides SPF record and macro syntax from Section 12, reusing the shared RFC 4408
 * productions. Does not evaluate policies, perform DNS lookups, expand macros, or
 * parse Received-SPF headers. Rule names retain the library's underscore convention.
 *
 * @see Rfc4408 Superseded by this RFC.
 * @see Rfc4291 IPv6 address syntax.
 * @see https://www.rfc-editor.org/rfc/rfc7208
 * @link https://www.rfc-editor.org/rfc/rfc7208.txt
 */
class Rfc7208 extends Rfc4408
{
    /** @var string */
    protected static $name = 'Rfc7208';

    /** @param array<string, C|Parser|Definition|callable> $overwrites */
    public function __construct(array $overwrites = [])
    {
        $abnf = new Rfc5234();
        $ipv6 = new Rfc4291();
        $rules = [
            'version' => C::text('v=spf1')->caseSensitive(false),
            'all' => C::text('all')->caseSensitive(false),
            'include' => C::sequence(
                C::text('include')->caseSensitive(false),
                C::text(':'),
                $this->rule('domain_spec'),
            ),
            'A' => C::sequence(
                C::text('a')->caseSensitive(false),
                C::sequence(C::text(':'), $this->rule('domain_spec'))->maybe(),
                $this->rule('dual_cidr_length'),
            ),
            'MX' => C::sequence(
                C::text('mx')->caseSensitive(false),
                C::sequence(C::text(':'), $this->rule('domain_spec'))->maybe(),
                $this->rule('dual_cidr_length'),
            ),
            'PTR' => C::sequence(
                C::text('ptr')->caseSensitive(false),
                C::sequence(C::text(':'), $this->rule('domain_spec'))->maybe(),
            ),
            'IP4' => C::sequence(
                C::text('ip4')->caseSensitive(false),
                C::text(':'),
                $this->rule('ip4_network'),
                $this->rule('ip4_cidr_length')->maybe(),
            ),
            'IP6' => C::sequence(
                C::text('ip6')->caseSensitive(false),
                C::text(':'),
                $this->rule('ip6_network'),
                $this->rule('ip6_cidr_length')->maybe(),
            ),
            'exists' => C::sequence(
                C::text('exists')->caseSensitive(false),
                C::text(':'),
                $this->rule('domain_spec'),
            ),
            'redirect' => C::sequence(
                C::text('redirect')->caseSensitive(false),
                C::text('='),
                $this->rule('domain_spec'),
            ),
            'explanation' => C::sequence(
                C::text('exp')->caseSensitive(false),
                C::text('='),
                $this->rule('domain_spec'),
            ),
            // A malformed known modifier must not fall back to unknown-modifier.
            'unknown_modifier' => C::sequence(
                C::not(C::choice(
                    C::text('redirect=')->caseSensitive(false),
                    C::text('exp=')->caseSensitive(false),
                )),
                $this->rule('name'),
                C::text('='),
                $this->rule('macro_string'),
            ),
            'ip4_cidr_length' => C::sequence(C::text('/'), C::integer(0, 32)),
            'ip6_cidr_length' => C::sequence(C::text('/'), C::integer(0, 128)),
            'qnum' => C::integer(0, 255),
            'ip6_network' => $ipv6->rule('ipv6_address'),
            // Right recursion reserves a nonempty domain-end instead of letting a
            // greedy macro-string consume it. Try the longest valid suffix first.
            'domain_spec' => C::first(
                C::sequence(
                    C::choice($this->rule('macro_expand'), $this->rule('macro_literal')),
                    $this->rule('domain_spec'),
                ),
                $this->rule('domain_end'),
            ),
            // A regex keeps the internal ABNF alternatives from greedily consuming
            // the final ALPHA/alphanum required by the toplabel production.
            'toplabel' => C::regex('/(?:[a-z0-9]+-[a-z0-9-]*[a-z0-9]|[a-z0-9]*[a-z][a-z0-9]*)/i'),
            'macro_letter' => C::set('slodiphcrtv')->caseSensitive(false),
            'transformers' => C::sequence(
                $abnf->rule('DIGIT')->many(),
                C::text('r')->caseSensitive(false)->maybe(),
            ),
        ];
        parent::__construct(array_merge($rules, $overwrites));
    }
}
