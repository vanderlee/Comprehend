<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Extraction\Parser;

use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;

/** Recognizes a sequence and lazily collects direct operand values, excluding trivia. */
class Collect extends Sequence
{
    use ExactClassSnapshotReuseTrait;

    protected function prepareSequenceValue(Success $success, array $children): void
    {
        $success->setValueMapper(static fn(): array => array_map(
            static fn(Success $child): mixed => $child->getValue(),
            $children,
        ));
    }
}
