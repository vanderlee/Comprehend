<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime\Scope;

use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Source;

/**
 * Maintains ParseSession's nested automatic-trivia policies. Recognizes spacing with recursive
 * automatic spacing suppressed and returns accepted trivia matches for tree and lifecycle
 * retention.
 */
trait SpacingContextTrait
{
    /** @var list<Parser|null> Intervening trivia only; boundaries are explicit parsers. */
    private array $spacers = [];

    public function pushSpacer(?Parser $trivia = null): void
    {
        $this->spacers[] = $trivia;
    }

    public function popSpacer(): void
    {
        array_pop($this->spacers);
    }

    public function skipSpacing(Source $source, int $offset, ?Success &$match = null): int|false
    {
        $match = null;
        $trivia = end($this->spacers);
        if ($this->isLexeme() || !$trivia instanceof Parser) {
            return 0;
        }
        // Trivia shares case, choice and diagnostics with the owning session,
        // but cannot recursively insert itself inside its own grammar.
        $this->pushLexeme();
        try {
            $attempt = $trivia->trivia()->recognizeInSession($source, $offset, $this);
        } finally {
            $this->popLexeme();
        }
        if (!$attempt instanceof Success) {
            return false;
        }
        $match = $attempt;

        return $match->length;
    }
}
