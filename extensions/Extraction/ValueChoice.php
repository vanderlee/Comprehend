<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Extraction;

use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Source\Source;

/** Uses ordinary choice policies while forwarding only the selected operand's lazy value. */
class ValueChoice extends Choice
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
