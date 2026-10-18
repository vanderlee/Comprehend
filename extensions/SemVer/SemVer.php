<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\SemVer;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\SemVer\Grammar\SemVer as GrammarSemVer;

/** Discoverable SemVer entry points over the shared specification grammars. */
final class SemVer extends SpecificationExtension
{
    protected const PARSERS = [
        'semver' => [GrammarSemVer::class, 'version'],
    ];
}
