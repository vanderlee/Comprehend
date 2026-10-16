<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Source\Source;

/** @internal Reuses Repeat's recognition, progress checks and trivia; projects a lazy list of values. */
final class ValueRepeat extends Repeat
{
    use ExactClassSnapshotReuseTrait;

    protected function success(Source $source, int $offset, int $length = 0, array|Success $children = []): Success
    {
        $success = parent::success($source, $offset, $length, $children);
        $items = $children instanceof Success ? [$children] : $children;
        $success->setValueMapper(
            static fn(): array => array_map(static fn(Success $item): mixed => $item->getValue(), $items),
        );

        return $success;
    }
}
