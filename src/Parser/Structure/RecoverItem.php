<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Recovery\BoundedItem;
use Vanderlee\Comprehend\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Source\Span;

/** A separated list that preserves malformed nonempty items and leaves its close to its owner. */
class RecoverItem extends SeparatedList
{
    use ExactClassSnapshotReuseTrait;

    public function __construct(
        Parser $item,
        Parser $separator,
        Parser $close,
        int $min = 0,
        ?int $max = null,
        bool $allowTrailing = false,
        ?Parser $ignore = null,
        ?int $maxSkippedBytes = null,
    ) {
        $boundary = new Choice($separator, $close);
        $probes = ['boundary' => $boundary];
        $hasIgnore = $ignore !== null;
        if ($ignore !== null) {
            $probes['ignore'] = $ignore;
        }
        $recoveringItem = new RecoverWith(
            new BoundedItem($item, $boundary),
            static fn(RecoveryContext $context): ?Span => $context->scanUntil(
                'boundary',
                ignore: $hasIgnore ? 'ignore' : null,
            ),
            $probes,
            $maxSkippedBytes,
        );
        parent::__construct($recoveringItem, $separator, $min, $max, allowTrailing: $allowTrailing);
    }
}
