<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

/** Allows facade preparation caching when a parser owns all grammar dependencies. */
interface ReusableSnapshot
{
    /**
     * This declaration must be pure and stable for the owned authoring copy. Repeated freeze
     * preparation must not depend on changing external state, including custom freeze overrides.
     * Composite dependencies must still be copied through GraphSnapshot and qualify separately.
     * Implementations should exclude subclasses unless those classes explicitly opt in.
     * Recognition results, semantic caches and effects remain local to each parse.
     */
    public function supportsSnapshotReuse(): bool;
}
