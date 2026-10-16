<?php

declare(strict_types=1);

namespace ComprehendComparison;

use Closure;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Benchmark-only prototype: predicates must be pure and receive one source unit. */
final class TakeWhile extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;

    /** @var Closure(string): bool */
    private readonly Closure $predicate;

    /** @param Closure(string): bool $predicate */
    public function __construct(Closure $predicate)
    {
        $this->predicate = $predicate;
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return true;
    }

    public function __toString(): string
    {
        return 'takeWhile';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $end = $byteOffset;
        while (($unit = $session->readSourceUnit($end)) !== null) {
            if (!(($this->predicate)($unit->rawText()))) {
                break;
            }
            $end = $unit->end;
        }

        return $this->success($source, $byteOffset, $end - $byteOffset);
    }
}
