<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Rfc3339;
use Vanderlee\Comprehend\Extensions\Library\Rfc9557;

/** Discoverable DateTime entry points over the shared specification grammars. */
final class DateTime extends SpecificationExtension
{
    protected const PARSERS = [
        'dateTime' => [Rfc3339::class, 'date_time'],
        'annotatedDateTime' => [Rfc9557::class, 'date_time_ext'],
        'dateTimeDate' => [Rfc3339::class, 'full_date'],
        'dateTimeTime' => [Rfc3339::class, 'full_time'],
    ];

    protected const VERSIONS = [
        'rfc3339' => Rfc3339::class,
        'rfc9557' => Rfc9557::class,
    ];
}
