<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Parser;

use UnexpectedValueException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Description of StubParser.
 *
 * @author Martijn
 */
class Stub extends Parser
{
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

    public function __toString(): string
    {
        /* @noinspection HtmlUnknownTag */
        return $this->parser ? (string) $this->parser : '<undefined>';
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        if ($this->parser === null) {
            throw new UnexpectedValueException('Missing parser');
        }
        $match = $this->parser->recognizeInSession($input, $offset, $context);
        if ($match instanceof Success) {
            return $this->success($input, $offset, $match->length, $match);
        }
        return $this->failure($input, $offset, $match->length);
    }
}
