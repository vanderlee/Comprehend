<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Binding;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Structure\SeparatedList as SyntaxList;

/** Projects item values while sharing separated-list recognition, diagnostics and lifecycle. */
class SeparatedList extends SyntaxList
{
    use ExactClassSnapshotReuseTrait;

    protected function prepareListValue(Success $success, array $items, array $selected): void
    {
        $success->setValueMapper(
            static fn(): array => array_map(static fn(Success $item): mixed => $item->getValue(), $items),
        );
    }
}
