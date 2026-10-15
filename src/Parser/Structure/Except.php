<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Match the first parser but not the second.
 * Essentially the same as (A - B) = (A + !B).
 *
 * @author Martijn
 */
class Except extends Parser
{
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
