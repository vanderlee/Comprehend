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
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\FailureDiagnosticAtStart;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Runtime\LexicalTable;
use Vanderlee\Comprehend\Source\Source;

/**
 * Implements negative lookahead without consuming input. Rejects when its child matches and
 * discards probe diagnostics so lookahead does not contaminate ordinary failures.
 */
class Not extends Parser implements AnalyzableParser, FailureDiagnosticAtStart, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;

    private ?LexicalTable $lexical = null;

    /** @internal Negative lookahead still owns diagnostic rollback and zero-width behavior. */
    public function prepareLexicalRecognition(): void
    {
        $this->lexical = LexicalTable::prepare($this->parser);
    }

    public function __construct(private Parser $parser) {}

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return [$this->parser];
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return true;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parser = $context->copy($this->parser);
        $this->lexical = null;
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return [$this->parser];
    }

    public function __toString(): string
    {
        return '!' . $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $diagnostics = $session->diagnosticState();
        try {
            $match = $this->lexical !== null && !$session->hasRecognitionLimits() && !$session->hasActiveSpacing()
                ? $this->lexical->attempt($source, $byteOffset, $session)
                : $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }

        if ($match instanceof Success) {
            return $this->failure($source, $byteOffset, $match->length);
        }

        return $this->success($source, $byteOffset);
    }
}
