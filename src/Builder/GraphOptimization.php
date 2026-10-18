<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

/** Optional preparation provider; replacements precede binding, preparation follows safety validation. */
interface GraphOptimization
{
    /** Return a detached shallow replacement; GraphSnapshot copies and registers its dependencies. */
    public function replace(Parser $parser): Parser;

    public function prepare(Parser $root): void;
}
