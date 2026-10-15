<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Overrides first, longest or shortest choice selection within a subtree. Scopes the
 * ParseSession preference consumed by Choice and restores it after recognition.
 */
class Prefer extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    public function __construct(private string $preference, private Parser $parser)
    {
        ChoicePolicy::assertValid($preference);
    }

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    protected function copyDependencies(GraphSnapshot $context): void
    {
        parent::copyDependencies($context);
        $this->parser = $context->copy($this->parser);
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
        return match ($this->preference) {
            ChoicePolicy::LONGEST => 'longest-of' . $this->parser,
            ChoicePolicy::SHORTEST => 'shortest-of' . $this->parser,
            default => (string) $this->parser,
        };
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $session->pushPreference($this->preference);
        try {
            return $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->popPreference();
        }
    }
}
