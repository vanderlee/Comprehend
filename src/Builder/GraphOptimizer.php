<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use SplObjectStorage;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Not;

/** Freeze-time replacements retain observable composition boundaries and custom parser behavior. */
final class GraphOptimizer
{
    /** Configure resolved patterns only after reference binding and structural validation finish. */
    public function prepare(Parser $root): void
    {
        $seen = new SplObjectStorage();
        $pending = [$root];
        while ($pending !== []) {
            $parser = array_pop($pending);
            if ($seen->contains($parser)) {
                continue;
            }
            $seen->attach($parser);
            if (in_array($parser::class, [Repeat::class, Choice::class, Not::class], true)) {
                $parser->prepareLexicalRecognition();
            }
            array_push($pending, ...$parser->children());
        }
    }

    public function replace(Parser $parser): Parser
    {
        // Exact classes only: subclasses may change recognition or snapshot contracts.
        return $parser::class === Repeat::class ? $parser->withOptimizedRecognition() : clone $parser;
    }
}
