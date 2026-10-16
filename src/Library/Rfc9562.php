<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Library;

use Vanderlee\Comprehend\Comprehend as C;

/**
 * Universally Unique IDentifiers (UUIDs).
 *
 * UUID text and UUID URNs.
 *
 * Obsoletes RFC 4122. The generic spelling accepts all bit patterns, including Nil
 * and Max; generation and version-specific interpretation are separate operations.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9562.txt
 */
class Rfc9562 extends AbstractSpecification
{
    protected const ROOT_RULE = 'uuid';

    protected function definitions(): array
    {
        return [
            'uuid' => C::regex('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i'),
            'uuid_urn' => C::sequence(C::text('urn:uuid:')->caseSensitive(false), $this->rule('uuid')),
        ];
    }
}
