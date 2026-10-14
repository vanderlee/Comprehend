<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Suppress spacing within a subtree, including local spacing overrides. */
class Lexeme extends Parser
{
    public function __construct(private Parser $parser)
    {
    }

    public function __toString(): string
    {
        return (string)$this->parser;
    }

    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $context->pushLexeme();
        try {
            return $this->parser->recognizeInSession($input, $offset, $context);
        } finally {
            $context->popLexeme();
        }
    }
}
