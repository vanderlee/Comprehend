<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Network\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Domain Names -- Implementation and Specification.
 *
 * DNS presentation names and zone-file records.
 *
 * Text rules implement Sections 3.5 and 5. Wire packets, compression pointers and DNS
 * resolution are outside this grammar. Unknown record data uses the generic text rule.
 *
 * @see Rfc1034 DNS concepts.
 * @see Rfc1123 Hostnames.
 * @link https://www.rfc-editor.org/rfc/rfc1035.txt
 */
class Rfc1035 extends AbstractSpecification
{
    protected const ROOT_RULE = 'domain_name';

    protected function definitions(): array
    {
        $space = C::regex('/[ \t]+/');
        $fold = C::regex('/(?:[ \t\r\n]+|;[^\r\n]*(?:\r\n|\n))+/');
        $optionalSpace = $space->maybe();
        $lineEnd = C::sequence(
            $optionalSpace,
            $this->rule('comment')->maybe(),
            C::first(C::text("\r\n"), C::text("\n"), C::end()),
        );
        $prefix = C::sequence(
            C::first(C::sequence(C::first(C::text('@'), $this->rule('domain_name')), $space), $space),
            C::longest(
                C::sequence($this->rule('ttl'), $space, C::sequence($this->rule('class'), $space)->maybe()),
                C::sequence($this->rule('class'), $space, C::sequence($this->rule('ttl'), $space)->maybe()),
            )->maybe(),
        );
        $typedData = function (C $separator): C {
            $name = C::first(C::text('@'), $this->rule('domain_name'));
            $number = C::regex('/[0-9]+/');

            return C::first(
                C::sequence(
                    C::text('AAAA')->caseSensitive(false),
                    $separator,
                    (new Rfc4291())->rule('ipv6_address'),
                ),
                C::sequence(C::text('A')->caseSensitive(false), $separator, (new Rfc3986())->rule('IPv4address')),
                C::sequence(C::regex('/(?:NS|CNAME|PTR|MD|MF|MB|MG|MR)/i'), $separator, $name),
                C::sequence(C::text('MX')->caseSensitive(false), $separator, $number, $separator, $name),
                C::sequence(
                    C::text('SOA')->caseSensitive(false),
                    $separator,
                    $name,
                    $separator,
                    $name,
                    C::sequence($separator, $number)->repeat(5, 5),
                ),
                C::sequence(
                    C::text('TXT')->caseSensitive(false),
                    $separator,
                    $this->rule('character_string'),
                    C::sequence($separator, $this->rule('character_string'))->many(),
                ),
                C::sequence(
                    C::not(C::regex('/(?:A|AAAA|NS|CNAME|PTR|MD|MF|MB|MG|MR|MX|SOA|TXT)(?=[ \t\r\n])/i')),
                    $this->rule('type'),
                    $separator,
                    $this->rule('rdata_atom'),
                    C::sequence($separator, $this->rule('rdata_atom'))->many(),
                ),
            );
        };
        // Parentheses can begin after the RR type. Keep that type outside the
        // folded data so physical newlines cannot silently join ordinary records.
        $folded = C::sequence(
            $this->rule('type'),
            $space,
            '(',
            $fold->maybe(),
            $this->rule('rdata_atom'),
            C::sequence($fold, $this->rule('rdata_atom'))->many(),
            $fold->maybe(),
            ')',
        );

        return [
            'escaped_octet' => C::regex('/\\\\(?:[0-9]{3}|[^\r\n])/')->guard(static function (
                ConcreteNode $node,
            ): bool {
                $text = substr($node->rawText(), 1);

                return !ctype_digit($text) || strlen($text) !== 3 || (int) $text <= 255;
            }),
            'label' => C::first(
                $this->rule('escaped_octet'),
                C::regex('/[^.\\\\\s;()"]+/'),
            )->some()->guard(static function (ConcreteNode $node): bool {
                $decoded = preg_replace('/\\\\(?:[0-9]{3}|[^\r\n])/', 'x', $node->rawText());

                return $decoded !== null && strlen($decoded) <= 63;
            }),
            'domain_name' => C::first(
                C::sequence(
                    $this->rule('label'),
                    C::sequence('.', $this->rule('label'))->many(),
                    C::text('.')->maybe(),
                ),
                C::text('.'),
            )->guard(static function (ConcreteNode $node): bool {
                $decoded = preg_replace('/\\\\(?:[0-9]{3}|[^\r\n])/', 'x', $node->rawText());

                return $decoded !== null && strlen(rtrim($decoded, '.')) <= 253;
            }),
            'character_string' => C::regex('/"(?:\\\\[^\r\n]|[^"\\\\\r\n])*"/'),
            'ttl' => C::regex('/[0-9]+/'),
            'class' => C::regex('/(?:IN|CS|CH|HS)/i'),
            'type' => C::regex('/(?:NSEC3PARAM|CDNSKEY|DNSKEY|RRSIG|NSEC3|NSEC|CNAME|HINFO|MINFO|DNAME|'
                . 'AAAA|CAA|SRV|NAPTR|HTTPS|SVCB|TLSA|SSHFP|CDS|DS|SPF|URI|CERT|LOC|NULL|WKS|SOA|TXT|PTR|'
                . 'MX|NS|MD|MF|MB|MG|MR|A|TYPE[0-9]+)/i'),
            'comment' => C::regex('/;[^\r\n]*/'),
            'rdata_atom' => C::first(
                $this->rule('character_string'),
                $this->rule('escaped_octet'),
                C::regex('/[^ \t\r\n;()"\\\\]+/'),
            ),
            'zone_record' => C::sequence($prefix, C::first($folded, $typedData($space)), $lineEnd),
            'zone_directive' => C::sequence(
                C::first(
                    C::sequence(C::text('$ORIGIN')->caseSensitive(false), $space, $this->rule('domain_name')),
                    C::sequence(C::text('$TTL')->caseSensitive(false), $space, $this->rule('ttl')),
                    C::sequence(
                        C::text('$INCLUDE')->caseSensitive(false),
                        $space,
                        C::first($this->rule('character_string'), C::regex('/[^ \t\r\n;]+/')),
                        C::sequence($space, $this->rule('domain_name'))->maybe(),
                    ),
                ),
                $lineEnd,
            ),
            'zone_file' => C::first(
                $this->rule('zone_directive'),
                $this->rule('zone_record'),
                C::sequence(
                    $optionalSpace,
                    $this->rule('comment')->maybe(),
                    C::first(C::text("\r\n"), C::text("\n")),
                ),
                C::sequence($optionalSpace, $this->rule('comment')),
            )->many(),
        ];
    }
}
