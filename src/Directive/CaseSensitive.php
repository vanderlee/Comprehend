<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Override case sensitivity within a subtree. */
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

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $context->pushCaseSensitivity($this->sensitivity);
        try {
            return $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->popCaseSensitivity();
        }
    }
}
