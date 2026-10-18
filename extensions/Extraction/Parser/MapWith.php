<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Extraction\Parser;

use Closure;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Sequence;

/** Recognizes both operands and combines their cached values lazily, in source order. */
class MapWith extends Sequence implements ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;

    private readonly Closure $combine;

    public function __construct(Parser $left, Parser $right, callable $combine)
    {
        parent::__construct($left, $right);
        $this->combine = Closure::fromCallable($combine);
    }

    protected function prepareSequenceValue(Success $success, array $children): void
    {
        [$left, $right] = $children;
        $combine = $this->combine;
        $success->setValueMapper(static function () use ($left, $right, $combine): mixed {
            $leftValue = $left->getValue();
            $rightValue = $right->getValue();

            return $combine($leftValue, $rightValue);
        });
    }
}
