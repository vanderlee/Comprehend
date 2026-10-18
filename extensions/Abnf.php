<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Rfc2234;
use Vanderlee\Comprehend\Extensions\Library\Rfc4234;
use Vanderlee\Comprehend\Extensions\Library\Rfc5234;
use Vanderlee\Comprehend\Extensions\Library\Rfc7405;

/** Discoverable Abnf entry points over the shared specification grammars. */
final class Abnf extends SpecificationExtension
{
    protected const VERSIONS = [
        'rfc2234' => Rfc2234::class,
        'rfc4234' => Rfc4234::class,
        'rfc5234' => Rfc5234::class,
        'rfc7405' => Rfc7405::class,
    ];
}
