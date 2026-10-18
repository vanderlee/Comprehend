<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Network\Grammar;

use Vanderlee\Comprehend\Builder\AbstractSpecification;
use Vanderlee\Comprehend\Comprehend as C;

/**
 * Uniform Resource Names (URNs).
 *
 * URNs with namespace-specific, resolution, query and fragment components.
 *
 * Obsoletes RFCs 2141 and 3406. Namespace registration is separate from syntax.
 *
 * @see Rfc3986 URI syntax.
 * @link https://www.rfc-editor.org/rfc/rfc8141.txt
 */
class Rfc8141 extends AbstractSpecification
{
    protected const ROOT_RULE = 'urn';

    protected function definitions(): array
    {
        $pchar = '(?:[A-Za-z0-9._~!$&\'()*+,;=:@-]|%[0-9a-fA-F]{2})';

        return [
            'nid' => C::regex('/[a-z0-9][a-z0-9-]{0,30}[a-z0-9]/i'),
            'nss' => C::regex('#' . $pchar . '(?:' . $pchar . '|/)*#'),
            'r_component' => C::regex('#' . $pchar . '(?:' . $pchar . '|/|\?(?!=))*#'),
            'q_component' => C::regex('#' . $pchar . '(?:' . $pchar . '|/|\?)*#'),
            'fragment' => (new Rfc3986())->rule('fragment'),
            'urn' => C::sequence(
                C::text('urn:')->caseSensitive(false),
                $this->rule('nid'),
                ':',
                $this->rule('nss'),
                C::sequence('?+', $this->rule('r_component'))->maybe(),
                C::sequence('?=', $this->rule('q_component'))->maybe(),
                C::sequence('#', $this->rule('fragment'))->maybe(),
            ),
        ];
    }
}
