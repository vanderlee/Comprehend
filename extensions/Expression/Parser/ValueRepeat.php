<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\ValueRepeat as ExtractionValueRepeat;

/** @internal Reuses Repeat's recognition, progress checks and trivia; projects a lazy list of values. */
final class ValueRepeat extends ExtractionValueRepeat
{
    use ExactClassSnapshotReuseTrait;
}
