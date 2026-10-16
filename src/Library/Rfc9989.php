<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;

/**
 * Domain-Based Message Authentication, Reporting, and Conformance (DMARC).
 *
 * DMARC DNS policy records.
 *
 * Obsoletes RFCs 7489 and 9091. Exposes the extensible record grammar and specialized
 * value rules; policy evaluation, tag interpretation and DNS discovery are separate.
 *
 * @see Rfc9990 Aggregate reports.
 * @see Rfc9991 Failure reports.
 * @link https://www.rfc-editor.org/rfc/rfc9989.txt
 */
class Rfc9989 extends AbstractSpecification
{
    protected const ROOT_RULE = 'dmarc_record';

    protected function definitions(): array
    {
        return [
            'wsp' => C::regex('/[ \t]+/')->maybe(),
            'equals' => C::sequence($this->rule('wsp'), '=', $this->rule('wsp')),
            'dmarc_sep' => C::sequence($this->rule('wsp'), ';', $this->rule('wsp')),
            'dmarc_version' => C::sequence(
                C::text('v')->caseSensitive(false),
                $this->rule('equals'),
                C::text('DMARC1')->caseSensitive(),
            ),
            'dmarc_tag' => C::sequence(
                C::regex('/[A-Za-z]+/'),
                $this->rule('equals'),
                C::regex('/[\x20-\x3a\x3c-\x7e]+/'),
            ),
            'dmarc_record' => C::sequence(
                $this->rule('dmarc_version'),
                C::sequence($this->rule('dmarc_sep'), $this->rule('dmarc_tag'))->many(),
                $this->rule('dmarc_sep')->maybe(),
            ),
            'dmarc_request' => C::regex('/(?:none|quarantine|reject)/i'),
            'dmarc_alignment' => C::set('rs')->caseSensitive(false),
            'dmarc_yorn' => C::set('yn')->caseSensitive(false),
            'dmarc_psd' => C::set('ynu')->caseSensitive(false),
            'reporting_uri' => (new Rfc3986())->rule('URI'),
        ];
    }
}
