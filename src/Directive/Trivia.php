<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Marks explicitly recognized whitespace or comments as concrete-tree trivia. Keeps their
 * source text and selected lifecycle operations while excluding them from named AST structure.
 */
class Trivia extends UnaryDirective
{
    public function __toString(): string
    {
        return (string) $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        return $this->parser->recognizeInSession($source, $byteOffset, $session);
    }
}
