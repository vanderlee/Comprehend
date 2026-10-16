<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Extensions\Recovery\Parser;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Extensions\Recovery\RecoveryContext;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Span;

/** Preserves invalid input through an explicit synchronization boundary. */
class RecoverUntil extends RecoverWith
{
    use ExactClassSnapshotReuseTrait;

    public function __toString(): string
    {
        return 'recover(' . $this->parser . ' until ' . $this->probes['boundary'] . ')';
    }

    public function __construct(
        Parser $parser,
        Parser $boundary,
        bool $consumeBoundary = false,
        bool $allowEof = false,
        ?int $maxSkippedBytes = null,
    ) {
        parent::__construct(
            $parser,
            static fn(RecoveryContext $context): ?Span => $context->scanUntil('boundary', $consumeBoundary, $allowEof),
            ['boundary' => $boundary],
            $maxSkippedBytes,
        );
    }
}
