<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Overrides case sensitivity for a parser subtree. Pushes a ParseSession policy and restores it
 * even when recognition fails or throws.
 */
class CaseSensitive extends Parser
{
    public function __construct(private readonly bool $sensitivity, private readonly Parser $parser) {}

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    public function __toString(): string
    {
        return ($this->sensitivity ? 'case' : 'no-case') . '( ' . $this->parser . ' )';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $session->pushCaseSensitivity($this->sensitivity);
        try {
            return $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->popCaseSensitivity();
        }
    }
}
