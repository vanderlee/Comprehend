<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\ValueChoice as ExtractionValueChoice;

/** @internal Reuses Choice's selection policies while forwarding the selected semantic value. */
final class ValueChoice extends ExtractionValueChoice
{
    use ExactClassSnapshotReuseTrait;
}
