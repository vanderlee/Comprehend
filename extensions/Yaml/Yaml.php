<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Yaml;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Yaml\Grammar\Yaml as GrammarYaml;

/** Discoverable Yaml entry points over the shared specification grammars. */
final class Yaml extends SpecificationExtension
{
    protected const PARSERS = [
        'yaml' => [GrammarYaml::class, 'stream'],
    ];
}
