<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Mail\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;

/**
 * Internet Message Format.
 *
 * Email addresses, mailboxes, headers and messages.
 *
 * Obsoletes RFC 2822. Modern Section 3 productions are exposed independently;
 * obsolete Section 4 address forms are not included.
 * The default root is mailbox, not a deliverability check.
 *
 * @see Rfc6854 Updated originator headers.
 * @see Rfc6532 Internationalized headers.
 * @link https://www.rfc-editor.org/rfc/rfc5322.txt
 */
class Rfc5322 extends AbstractSpecification
{
    protected const ROOT_RULE = 'mailbox';

    /** @return array<string, C> */
    protected function definitions(): array
    {
        $cfws = $this->rule('cfws')->maybe();

        return [
            'fws' => C::regex('/(?:[ \t]|\r\n(?=[ \t]))+/'),
            'quoted_pair' => C::regex('/\\\\[\x09\x20-\x7e]/'),
            'ctext' => C::regex('/[\x21-\x27\x2a-\x5b\x5d-\x7e]+/'),
            'comment' => C::sequence(
                '(',
                C::first(
                    $this->rule('fws'),
                    $this->rule('ctext'),
                    $this->rule('quoted_pair'),
                    $this->rule('comment'),
                )->many(),
                ')',
            ),
            'cfws' => C::first($this->rule('fws'), $this->rule('comment'))->some(),
            'atext' => C::regex('/[A-Za-z0-9!#$%&\'*+\-\/=?^_`{|}~]+/'),
            'atom' => C::sequence($cfws, $this->rule('atext'), $cfws),
            'dot_atom_text' => C::sequence($this->rule('atext'), C::sequence('.', $this->rule('atext'))->many()),
            'dot_atom' => C::sequence($cfws, $this->rule('dot_atom_text'), $cfws),
            'qtext' => C::regex('/[\x21\x23-\x5b\x5d-\x7e]+/'),
            'quoted_string' => C::sequence(
                $cfws,
                '"',
                C::first($this->rule('fws'), $this->rule('qtext'), $this->rule('quoted_pair'))->many(),
                '"',
                $cfws,
            ),
            'word' => C::first($this->rule('quoted_string'), $this->rule('atom')),
            'phrase' => $this->rule('word')->some(),
            'display_name' => $this->rule('phrase'),
            'local_part' => C::first($this->rule('dot_atom'), $this->rule('quoted_string')),
            'dtext' => C::regex('/[\x21-\x5a\x5e-\x7e]+/'),
            'domain_literal' => C::sequence(
                $cfws,
                '[',
                C::first($this->rule('fws'), $this->rule('dtext'))->many(),
                ']',
                $cfws,
            ),
            'domain' => C::first($this->rule('dot_atom'), $this->rule('domain_literal')),
            'addr_spec' => C::sequence($this->rule('local_part'), '@', $this->rule('domain')),
            'angle_addr' => C::sequence($cfws, '<', $this->rule('addr_spec'), '>', $cfws),
            'name_addr' => C::sequence($this->rule('display_name')->maybe(), $this->rule('angle_addr')),
            'mailbox' => C::longest($this->rule('name_addr'), $this->rule('addr_spec')),
            'mailbox_list' => C::sequence(
                $this->rule('mailbox'),
                C::sequence(',', $this->rule('mailbox'))->many(),
            ),
            'group' => C::sequence(
                $this->rule('display_name'),
                ':',
                C::first($this->rule('mailbox_list'), $this->rule('cfws'))->maybe(),
                ';',
                $cfws,
            ),
            'address' => C::longest($this->rule('group'), $this->rule('mailbox')),
            'address_list' => C::sequence(
                $this->rule('address'),
                C::sequence(',', $this->rule('address'))->many(),
            ),
            'msg_id' => C::sequence(
                $cfws,
                '<',
                $this->rule('dot_atom_text'),
                '@',
                C::first($this->rule('dot_atom_text'), C::regex('/\[[\x21-\x5a\x5e-\x7e]*\]/')),
                '>',
                $cfws,
            ),
            'date_time' => C::regex('/(?:[ \t]*(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),)?[ \t]*[0-9]{1,2}[ \t]+'
                . '(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ \t]+[0-9]{4,}[ \t]+'
                . '[0-9]{2}:[0-9]{2}(?::[0-9]{2})?[ \t]+[+-][0-9]{4}(?:[ \t]+)?/i'),
            'field_name' => C::regex('/[\x21-\x39\x3b-\x7e]+/'),
            'unstructured' => C::first($this->rule('fws'), C::regex('/[\x21-\x7e]+/'))->many(),
            'field' => C::sequence($this->rule('field_name'), ':', $this->rule('unstructured'), "\r\n"),
            'fields' => $this->rule('field')->many(),
            'body' => C::regex('/(?:[\x01-\x09\x0b\x0c\x0e-\x7f]{0,998}\r\n)*'
                . '[\x01-\x09\x0b\x0c\x0e-\x7f]{0,998}/')->maybe(),
            'message' => C::sequence($this->rule('fields'), C::sequence("\r\n", $this->rule('body'))->maybe()),
            'from' => C::sequence(C::text('From:')->caseSensitive(false), $this->rule('mailbox_list'), "\r\n"),
            'sender' => C::sequence(C::text('Sender:')->caseSensitive(false), $this->rule('mailbox'), "\r\n"),
            'to' => C::sequence(C::text('To:')->caseSensitive(false), $this->rule('address_list'), "\r\n"),
            'subject' => C::sequence(C::text('Subject:')->caseSensitive(false), $this->rule('unstructured'), "\r\n"),
        ];
    }
}
