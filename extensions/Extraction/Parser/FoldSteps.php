<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Extraction\Parser;

use Closure;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Source\Source;

/** @internal Captures accepted step matches in a per-recognition lazy reduction, not a value array. */
final class FoldSteps extends Repeat
{
    use ExactClassSnapshotReuseTrait;

    private readonly Closure $combine;

    public function __construct(Parser $step, callable $combine, int $min, ?int $max)
    {
        parent::__construct($step, $min, $max);
        $this->combine = Closure::fromCallable($combine);
    }

    protected function success(Source $source, int $offset, int $length = 0, array|Success $children = []): Success
    {
        $success = parent::success($source, $offset, $length, $children);
        $items = $children instanceof Success ? [$children] : $children;
        $combine = $this->combine;
        $success->setValueMapper(static fn(): Closure => static function (mixed $value) use ($items, $combine): mixed {
            foreach ($items as $item) {
                $value = $combine($value, $item->getValue());
            }

            return $value;
        });

        return $success;
    }
}
