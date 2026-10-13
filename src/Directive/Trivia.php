<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Parser\Parser;

/** Mark explicitly recognized whitespace/comments without changing legacy outputs. */
class Trivia extends Parser
{
    use ArgumentsTrait;

    private Parser $parser;

    public function __construct($parser)
    {
        $this->parser = self::getArgument($parser);
    }

    protected function parse(&$input, $offset, Context $context)
    {
        return $this->parser->parseInContext($input, $offset, $context);
    }

    public function __toString()
    {
        return (string) $this->parser;
    }
}
