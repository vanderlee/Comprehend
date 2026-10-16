<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\FailureDiagnosticAtStart;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Matches a positive operand only when an excluded operand fails at the same offset. Enables
 * grammar subtraction while keeping exclusion-probe diagnostics out of the selected result.
 *
 * @author Martijn
 */
class Except extends Parser implements AnalyzableParser, FailureDiagnosticAtStart, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;

    private Parser $parserMatch;
    private Parser $parserNot;

    /**
     * @param Parser $match
     * @param Parser $not
     */
    public function __construct(Parser $match, Parser $not)
    {
        $this->parserMatch = $match;
        $this->parserNot = $not;
    }

    /** @internal Positive operand for graph analysis. */
    public function getMatchParser(): Parser
    {
        return $this->parserMatch;
    }

    /** @internal Excluded operand for graph analysis. */
    public function getExcludedParser(): Parser
    {
        return $this->parserNot;
    }

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return [$this->parserMatch, $this->parserNot];
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $lookup->get($this->parserMatch);
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parserMatch = $context->copy($this->parserMatch);
        $this->parserNot = $context->copy($this->parserNot);
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return [$this->parserMatch, $this->parserNot];
    }

    public function __toString(): string
    {
        return '( ' . $this->parserMatch . ' - ' . $this->parserNot . ' )';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $match = $this->parserMatch->recognizeInSession($source, $byteOffset, $session);
        $diagnostics = $session->diagnosticState();
        try {
            $not = $this->parserNot->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }
        if ($match instanceof Success && $not instanceof Failure) {
            return $this->success($source, $byteOffset, $match->length, $match);
        }
        if ($match instanceof Success) {
            $session->recordFailure($byteOffset, 'Except');
        }

        return $this->failure($source, $byteOffset, min($match->length, $not->length));
    }
}
