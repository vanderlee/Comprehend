<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\SemVer as GrammarSemVer;

/** Discoverable SemVer entry points over the shared specification grammars. */
final class SemVer extends SpecificationExtension
{
    protected const PARSERS = [
        'semver' => [GrammarSemVer::class, 'version'],
    ];
}
