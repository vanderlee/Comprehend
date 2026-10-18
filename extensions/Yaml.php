<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Yaml as GrammarYaml;

/** Discoverable Yaml entry points over the shared specification grammars. */
final class Yaml extends SpecificationExtension
{
    protected const PARSERS = [
        'yaml' => [GrammarYaml::class, 'stream'],
    ];
}
