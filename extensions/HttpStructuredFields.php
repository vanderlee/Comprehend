<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Rfc9651;

/** Discoverable HttpStructuredFields entry points over the shared specification grammars. */
final class HttpStructuredFields extends SpecificationExtension
{
    protected const PARSERS = [
        'structuredFieldItem' => [Rfc9651::class, 'sf_item'],
        'structuredFieldList' => [Rfc9651::class, 'sf_list'],
        'structuredFieldDictionary' => [Rfc9651::class, 'sf_dictionary'],
    ];

    protected const VERSIONS = [
        'rfc9651' => Rfc9651::class,
    ];
}
