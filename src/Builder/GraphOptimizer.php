<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;

/** Freeze-time replacements retain observable composition boundaries and custom parser behavior. */
final class GraphOptimizer
{
    public function replace(Parser $parser): Parser
    {
        // Exact classes only: subclasses may change recognition or snapshot contracts.
        return $parser::class === Repeat::class ? $parser->withOptimizedRecognition() : clone $parser;
    }
}
