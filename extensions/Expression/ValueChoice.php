<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Expression;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Source\Source;

/** @internal Reuses Choice's selection policies while forwarding the selected semantic value. */
final class ValueChoice extends Choice
{
    use ExactClassSnapshotReuseTrait;

    protected function success(Source $source, int $offset, int $length = 0, array|Success $children = []): Success
    {
        $success = parent::success($source, $offset, $length, $children);
        if ($children instanceof Success) {
            $success->setValueMapper(static fn(): mixed => $children->getValue());
        }

        return $success;
    }
}
