<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Library;

use Vanderlee\Comprehend\Comprehend as C;

/**
 * Domain-Based Message Authentication, Reporting, and Conformance (DMARC) Failure Reporting.
 *
 * DMARC failure-report identity alignment headers.
 *
 * Obsoletes the failure-report portions of RFC 7489. Implements the new Section 4
 * header syntax; ARF/MIME transport and failure-report policy are separate layers.
 *
 * @see Rfc9989 DMARC policy records.
 * @see Rfc2046 MIME transport components.
 * @link https://www.rfc-editor.org/rfc/rfc9991.txt
 */
class Rfc9991 extends AbstractSpecification
{
    protected const ROOT_RULE = 'id_align';

    protected function definitions(): array
    {
        $cfws = (new Rfc5322())->rule('cfws')->maybe();

        return [
            'dmarc_method' => C::regex('/(?:dkim|spf)/i'),
            'alignment_value' => C::first(
                C::text('none')->caseSensitive(false),
                C::sequence(
                    C::text('dkim')->caseSensitive(false),
                    C::sequence($cfws, ',', $cfws, C::text('spf')->caseSensitive(false))->maybe(),
                ),
                C::sequence(
                    C::text('spf')->caseSensitive(false),
                    C::sequence($cfws, ',', $cfws, C::text('dkim')->caseSensitive(false))->maybe(),
                ),
            ),
            'id_align' => C::sequence(
                C::text('Identity-Alignment:')->caseSensitive(false),
                $cfws,
                $this->rule('alignment_value'),
                $cfws,
            ),
            'identity_alignment_header' => C::sequence($this->rule('id_align'), "\r\n"),
            'auth_failure' => C::text('dmarc')->caseSensitive(false),
        ];
    }
}
