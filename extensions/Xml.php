<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions;

use Vanderlee\Comprehend\Extensions\Library\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Library\Xml as GrammarXml;

/** Discoverable Xml entry points over the shared specification grammars. */
final class Xml extends SpecificationExtension
{
    protected const PARSERS = [
        'xmlDocument' => [GrammarXml::class, 'document'],
    ];
}
