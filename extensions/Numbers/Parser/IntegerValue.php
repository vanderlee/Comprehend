<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Numbers\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Source\Source;

/** Exposes the recognition-time validated integer through the usual lazy value cache. */
class IntegerValue extends IntegerNumber
{
    use ExactClassSnapshotReuseTrait;

    protected function numericSuccess(Source $source, int $offset, int $length, int $value): Success
    {
        $success = $this->success($source, $offset, $length);
        $success->setValueMapper(static fn(): int => $value);

        return $success;
    }
}
