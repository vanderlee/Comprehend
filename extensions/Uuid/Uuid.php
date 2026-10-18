<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Uuid;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Uuid\Grammar\Rfc9562;

/** Discoverable Uuid entry points over the shared specification grammars. */
final class Uuid extends SpecificationExtension
{
    protected const PARSERS = [
        'uuid' => [Rfc9562::class, 'uuid'],
        'uuidUrn' => [Rfc9562::class, 'uuid_urn'],
    ];

    protected const VERSIONS = [
        'rfc9562' => Rfc9562::class,
    ];
}
