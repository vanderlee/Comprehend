<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Rfc6901;
use Vanderlee\Comprehend\Extensions\Library\Rfc8259;
use Vanderlee\Comprehend\Extensions\Library\Rfc9535;

/** Discoverable Json entry points over the shared specification grammars. */
final class Json extends SpecificationExtension
{
    protected const PARSERS = [
        'json' => [Rfc8259::class, 'json_text'],
        'jsonPointer' => [Rfc6901::class, 'json_pointer'],
        'jsonPath' => [Rfc9535::class, 'jsonpath_query'],
        'jsonString' => [Rfc8259::class, 'string'],
        'jsonNumber' => [Rfc8259::class, 'number'],
    ];

    protected const VERSIONS = [
        'rfc6901' => Rfc6901::class,
        'rfc8259' => Rfc8259::class,
        'rfc9535' => Rfc9535::class,
    ];
}
