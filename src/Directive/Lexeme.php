<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/** Suppress spacing within a subtree, including local spacing overrides. */
class Lexeme extends Parser
{
    use ArgumentsTrait;

    private Parser $parser;

    public function __construct($parser)
    {
        $this->parser = self::getArgument($parser);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $context->pushLexeme();
        try {
            return $this->parser->parseInContext($input, $offset, $context);
        } finally {
            $context->popLexeme();
        }
    }

    public function __toString()
    {
        return (string) $this->parser;
    }
}
