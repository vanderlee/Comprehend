<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Binding;

use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Source;

/** Selects a present item's lazy value, or a literal default when no item was accepted. */
class OptionalValue extends Repeat
{
    use ExactClassSnapshotReuseTrait;

    public function __construct(Parser $parser, private readonly mixed $default = null)
    {
        parent::__construct($parser, 0, 1);
    }

    protected function success(Source $source, int $offset, int $length = 0, array|Success $children = []): Success
    {
        $success = parent::success($source, $offset, $length, $children);
        $item = $children instanceof Success ? $children : ($children[0] ?? null);
        $default = $this->default;
        $success->setValueMapper(static fn(): mixed => $item !== null ? $item->getValue() : $default);

        return $success;
    }
}
