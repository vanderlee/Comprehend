<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\LanguageTag;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\LanguageTag\Grammar\Rfc5646;

/** Discoverable LanguageTag entry points over the shared specification grammars. */
final class LanguageTag extends SpecificationExtension
{
    protected const PARSERS = [
        'languageTag' => [Rfc5646::class, 'language_tag'],
    ];

    protected const VERSIONS = [
        'rfc5646' => Rfc5646::class,
    ];
}
