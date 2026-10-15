<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Implements negative lookahead without consuming input. Rejects when its child matches and
 * discards probe diagnostics so lookahead does not contaminate ordinary failures.
 */
class Not extends Parser implements AnalyzableParser
{
    use DefaultAnalysisTrait;

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

    public function __toString(): string
    {
        return '!' . $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $diagnostics = $session->diagnosticState();
        try {
            $match = $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->restoreDiagnostics($diagnostics);
        }

        return $match instanceof Success
            ? $this->failure($source, $byteOffset, $match->length)
            : $this->success($source, $byteOffset);
    }
}
