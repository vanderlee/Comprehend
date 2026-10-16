<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Closure;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Span;

/** Reads while a pure predicate accepts raw source units, without case folding. */
final class ReadWhile extends ReadRun
{
    /** @var Closure(string): bool */
    private readonly Closure $predicate;

    /** @param callable(string): bool $predicate */
    public function __construct(callable $predicate, int $min = 0, ?int $max = null)
    {
        parent::__construct($min, $max);
        $this->predicate = Closure::fromCallable($predicate);
    }

    public function __toString(): string
    {
        return 'readWhile(predicate)';
    }

    protected function acceptsUnit(Span $unit, ParseSession $session): bool
    {
        return ($this->predicate)($unit->rawText());
    }
}
