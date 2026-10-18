<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Extraction\Parser;

use Closure;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Parser\Parser;

/** Recognizes a head and repeated steps, then reduces their values from left to right. */
class FoldLeft extends MapWith
{
    use ExactClassSnapshotReuseTrait;

    public function __construct(Parser $head, Parser $step, callable $combine, int $min = 0, ?int $max = null)
    {
        parent::__construct(
            $head,
            new FoldSteps($step, $combine, $min, $max),
            static fn(mixed $value, Closure $reduce): mixed => $reduce($value),
        );
    }
}
