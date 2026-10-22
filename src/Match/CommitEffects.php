<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\Span;

/** @internal Registration snapshot; the executing match supplies its owner only at commit. */
final readonly class CommitEffects
{
    /**
     * @param list<callable> $effects
     * @param array<callable> $assignments
     */
    public function __construct(
        private Span $span,
        private array $effects,
        private array $assignments,
    ) {
        // No owner reference: discarded recognition records can be released immediately.
    }

    public function __invoke(Success $success): void
    {
        foreach ($this->assignments as $assignment) {
            $assignment($this->span->rawText());
        }
        $result = new ParseResult($success);
        foreach ($this->effects as $effect) {
            $effect($result);
        }
    }
}
