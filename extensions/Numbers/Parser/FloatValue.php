<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Numbers\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Source\Source;

/** Exposes the recognition-time validated float through the usual lazy value cache. */
class FloatValue extends FloatNumber
{
    use ExactClassSnapshotReuseTrait;

    protected function numericSuccess(Source $source, int $offset, int $length, float $value): Success
    {
        $success = $this->success($source, $offset, $length);
        $success->setValueMapper(static fn(): float => $value);

        return $success;
    }
}
