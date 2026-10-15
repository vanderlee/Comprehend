<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Override or disable intervening trivia within a subtree. */
class Space extends Parser
{
    public function __construct(private ?Parser $spacer, private Parser $parser) {}

    /** @internal Structural operand for graph analysis. */
    public function getParser(): Parser
    {
        return $this->parser;
    }

    public function __toString(): string
    {
        return (string) $this->parser;
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $context->pushSpacer($this->spacer);
        try {
            return $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->popSpacer();
        }
    }
}
