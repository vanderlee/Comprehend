<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Parser\Structure\ValueChoice as CoreValueChoice;

/** @internal Reuses Choice's selection policies while forwarding the selected semantic value. */
final class ValueChoice extends CoreValueChoice
{
    use ExactClassSnapshotReuseTrait;
}
