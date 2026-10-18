<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Abnf;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc2234;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc4234;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc5234;
use Vanderlee\Comprehend\Extensions\Abnf\Grammar\Rfc7405;

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
