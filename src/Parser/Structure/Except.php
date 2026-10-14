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
    private $parserMatch;
    private $parserNot;

    /**
     * @param Parser $match
     * @param Parser $not
     */
    public function __construct(Parser $match, Parser $not)
    {
        $this->parserMatch = $match;
        $this->parserNot = $not;
    }

    public function __toString(): string
    {
        return '( ' . $this->parserMatch . ' - ' . $this->parserNot . ' )';
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $match = $this->parserMatch->recognizeInSession($input, $offset, $context);
        $diagnostics = $context->diagnosticState();
        try {
            $not = $this->parserNot->recognizeInSession($input, $offset, $context);
        } finally {
            $context->restoreDiagnostics($diagnostics);
        }
        if ($match instanceof Success && $not instanceof Failure) {
            return $this->success($input, $offset, $match->length, $match);
        }
        if ($match instanceof Success) {
            $context->recordFailure($offset, 'Except');
        }
        return $this->failure($input, $offset, min($match->length, $not->length));
    }
}
