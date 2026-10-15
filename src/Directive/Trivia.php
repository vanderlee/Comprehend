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
 * Marks explicitly recognized whitespace or comments as concrete-tree trivia. Keeps their
 * source text and selected lifecycle operations while excluding them from named AST structure.
 */
class Trivia extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    public function __construct(private Parser $parser) {}

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return [$this->parser];
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
        return $this->parser->recognizeInSession($source, $byteOffset, $session);
    }
}
