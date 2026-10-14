<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/** Mark explicitly recognized whitespace/comments with independent raw source access. */
class Trivia extends Parser
{
    private Parser $parser;
    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        return $this->parser->recognizeInSession($input, $offset, $context);
    }
    public function __toString(): string
    {
        return (string) $this->parser;
    }
}
