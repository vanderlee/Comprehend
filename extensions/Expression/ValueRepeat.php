<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Extensions\Extraction\ValueRepeat as ExtractionValueRepeat;

/** @internal Reuses Repeat's recognition, progress checks and trivia; projects a lazy list of values. */
final class ValueRepeat extends ExtractionValueRepeat
{
    use ExactClassSnapshotReuseTrait;
}
