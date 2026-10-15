<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser;

use UnexpectedValueException;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\ForwardingAnalysisTrait;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Provides an explicitly bound placeholder for recursive object grammars. Delegates to its
 * assigned Parser; graph freezing rejects stubs that have no target.
 *
 * @author Martijn
 */
class Stub extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    /**
     * @var Parser|null
     */
    private ?Parser $parser = null;

    public function getParser(): ?Parser
    {
        return $this->parser;
    }

    public function setParser(Parser $parser): static
    {
        $this->parser = $parser;

        return $this;
    }

    public function analysisError(): ?string
    {
        return $this->parser === null ? 'Unresolved stub' : null;
    }

    protected function analysisTarget(): Parser
    {
        return $this->parser ?? throw new \LogicException('Unresolved stub');
    }

    public function __toString(): string
    {
        /* @noinspection HtmlUnknownTag */
        return $this->parser ? (string) $this->parser : '<undefined>';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        if ($this->parser === null) {
            throw new UnexpectedValueException('Missing parser');
        }
        $match = $this->parser->recognizeInSession($source, $byteOffset, $session);
        if ($match instanceof Success) {
            return $this->success($source, $byteOffset, $match->length, $match);
        }

        return $this->failure($source, $byteOffset, $match->length);
    }
}
