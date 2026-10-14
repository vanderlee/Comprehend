<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/** Suppress spacing within a subtree, including local spacing overrides. */
class Lexeme extends Parser
{
    private Parser $parser;
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
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
    public function __toString(): string
    {
        return (string) $this->parser;
    }
}
