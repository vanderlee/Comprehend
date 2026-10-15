<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Negative lookahead; a successful probe rejects without consuming input. */
class Not extends Parser
{
    public function __construct(private Parser $parser) {}

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
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
