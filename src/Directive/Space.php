<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Configures or disables automatic intervening trivia for a parser subtree. Scopes the spacer
 * in ParseSession so enclosing grammars retain their own spacing policy.
 */
class Space extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    public function __construct(private ?Parser $spacer, private Parser $parser) {}

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return $this->spacer === null ? [$this->parser] : [$this->spacer, $this->parser];
    }

    protected function analysisTarget(): Parser
    {
        return $this->getParser();
    }

    public function __toString(): string
    {
        return (string) $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $session->pushSpacer($this->spacer);
        try {
            return $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->popSpacer();
        }
    }
}
