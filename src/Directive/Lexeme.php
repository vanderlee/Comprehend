<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Directive;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Treats a subtree as a contiguous lexeme by suppressing automatic spacing. Uses ParseSession
 * scope to override even nested local spacing policies and restores the previous scope
 * afterward.
 */
class Lexeme extends UnaryDirective
{
    public function __toString(): string
    {
        return (string) $this->parser;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $session->pushLexeme();
        try {
            return $this->parser->recognizeInSession($source, $byteOffset, $session);
        } finally {
            $session->popLexeme();
        }
    }
}
