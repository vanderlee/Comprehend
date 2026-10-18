<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Xml;

use Vanderlee\Comprehend\Syntax\SpecificationExtension;
use Vanderlee\Comprehend\Extensions\Xml\Grammar\Xml as GrammarXml;

/** Discoverable Xml entry points over the shared specification grammars. */
final class Xml extends SpecificationExtension
{
    protected const PARSERS = [
        'xmlDocument' => [GrammarXml::class, 'document'],
    ];
}
