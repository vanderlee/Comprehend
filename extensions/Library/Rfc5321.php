<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Simple Mail Transfer Protocol.
 *
 * SMTP envelope addresses, paths, commands and replies.
 *
 * Obsoletes RFC 2821. This grammar does not implement an SMTP session or delivery.
 *
 * @see Rfc6531 Internationalized SMTP.
 * @see Rfc5322 Header addresses use different whitespace rules.
 * @link https://www.rfc-editor.org/rfc/rfc5321.txt
 */
class Rfc5321 extends AbstractSpecification
{
    protected const ROOT_RULE = 'mailbox';

    /** @return array<string, C> */
    protected function definitions(): array
    {
        $host = new Rfc1123();
        $ipv6 = new Rfc4291();
        $uri = new Rfc3986();

        return [
            'domain' => C::sequence(
                $host->rule('label'),
                C::sequence('.', $host->rule('label'))->many(),
            ),
            'atom' => C::regex('/[A-Za-z0-9!#$%&\'*+\-\/=?^_`{|}~]+/'),
            'dot_string' => C::sequence($this->rule('atom'), C::sequence('.', $this->rule('atom'))->many()),
            'quoted_string' => C::regex('/"(?:[\x20-\x21\x23-\x5b\x5d-\x7e]|\\\\[\x20-\x7e])*"/'),
            'local_part' => C::first($this->rule('quoted_string'), $this->rule('dot_string')),
            'address_literal' => C::sequence('[', C::longest(
                C::sequence(C::text('IPv6:')->caseSensitive(false), $ipv6->rule('ipv6_address')),
                $uri->rule('IPv4address'),
                C::regex('/[A-Za-z0-9-]*[A-Za-z0-9]:[\x21-\x5a\x5e-\x7e]+/'),
            ), ']'),
            'mailbox' => C::sequence(
                $this->rule('local_part'),
                '@',
                C::first($this->rule('address_literal'), $this->rule('domain')),
            ),
            'at_domain' => C::sequence('@', $this->rule('domain')),
            'path' => C::sequence('<', C::sequence(
                $this->rule('at_domain'),
                C::sequence(',', $this->rule('at_domain'))->many(),
                ':',
            )->maybe(), $this->rule('mailbox'), '>'),
            'reverse_path' => C::first(C::text('<>'), $this->rule('path')),
            'forward_path' => C::longest($this->rule('path'), C::text('<Postmaster>')->caseSensitive(false)),
            'esmtp_keyword' => C::regex('/[A-Za-z0-9][A-Za-z0-9-]*/'),
            'esmtp_param' => C::sequence(
                $this->rule('esmtp_keyword'),
                C::sequence('=', C::regex('/[\x21-\x3c\x3e-\x7e]+/'))->maybe(),
            ),
            'mail' => C::sequence(
                C::text('MAIL FROM:')->caseSensitive(false),
                $this->rule('reverse_path'),
                C::sequence(' ', $this->rule('esmtp_param'))->many(),
                "\r\n",
            ),
            'rcpt' => C::sequence(
                C::text('RCPT TO:')->caseSensitive(false),
                $this->rule('forward_path'),
                C::sequence(' ', $this->rule('esmtp_param'))->many(),
                "\r\n",
            ),
            'reply_code' => C::regex('/[2-5][0-5][0-9]/'),
            'reply_line' => C::sequence(
                $this->rule('reply_code'),
                C::regex('/(?:[ -][\x09\x20-\x7e]*)?/')->maybe(),
                "\r\n",
            ),
            'reply' => C::sequence(
                C::sequence($this->rule('reply_code'), '-', C::regex('/[\x09\x20-\x7e]+/')->maybe(), "\r\n")->many(),
                $this->rule('reply_code'),
                C::sequence(' ', C::regex('/[\x09\x20-\x7e]+/')->maybe())->maybe(),
                "\r\n",
            )->guard(static function (ConcreteNode $node): bool {
                preg_match_all('/(?:\A|\r\n)([0-9]{3})/', $node->rawText(), $codes);

                return count(array_unique($codes[1])) === 1;
            }),
            'helo' => C::sequence(
                C::text('HELO ')->caseSensitive(false),
                C::first($this->rule('address_literal'), $this->rule('domain')),
                "\r\n",
            ),
            'ehlo' => C::sequence(
                C::text('EHLO ')->caseSensitive(false),
                C::first($this->rule('address_literal'), $this->rule('domain')),
                "\r\n",
            ),
            'command' => C::first(
                $this->rule('mail'),
                $this->rule('rcpt'),
                $this->rule('helo'),
                $this->rule('ehlo'),
                C::sequence(C::regex('/(?:DATA|RSET|QUIT)/i'), "\r\n"),
                C::sequence(
                    C::regex('/(?:VRFY|EXPN)/i'),
                    ' ',
                    C::first($this->rule('quoted_string'), $this->rule('atom')),
                    "\r\n",
                ),
                C::sequence(
                    C::regex('/(?:HELP|NOOP)/i'),
                    C::sequence(' ', C::first($this->rule('quoted_string'), $this->rule('atom')))->maybe(),
                    "\r\n",
                ),
            ),
        ];
    }
}
