<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Rfc5646;

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
