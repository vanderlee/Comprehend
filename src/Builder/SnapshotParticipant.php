<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

/** Owner-defined preparation of a detached parser or definition snapshot. */
interface SnapshotParticipant
{
    /** Register a destination before copying dependencies, then return that registered object. */
    public function snapshot(GraphSnapshot $context): Parser|Definition;
}
