<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime\Scope;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Directive\Trivia;
use WeakMap;

/**
 * Maintains ParseSession's nested automatic-trivia policies. Recognizes spacing with recursive
 * automatic spacing suppressed and returns accepted trivia matches for tree and lifecycle
 * retention.
 */
trait SpacingContextTrait
{
    /** @var list<Parser|null> Intervening trivia only; boundaries are explicit parsers. */
    private array $spacers = [];
    /** @var WeakMap<Parser, Trivia>|null Wrappers are grammar objects; matches remain per attempt. */
    private ?WeakMap $triviaParsers = null;

    public function pushSpacer(?Parser $trivia = null): void
    {
        $this->spacers[] = $trivia;
    }

    public function popSpacer(): void
    {
        array_pop($this->spacers);
    }

    /** Zero means no spacing was consumed; false means the active trivia parser rejected input. */
    public function skipSpacing(Source $source, int $offset, ?Success &$match = null): int|false
    {
        $match = null;
        $trivia = end($this->spacers);
        if ($this->isLexeme() || !$trivia instanceof Parser) {
            return 0;
        }

        $attempt = $this->recognizeTrivia($trivia, $source, $offset);
        if (!$attempt instanceof Success) {
            return false;
        }
        $match = $attempt;

        return $match->length;
    }

    private function recognizeTrivia(Parser $trivia, Source $source, int $byteOffset): AbstractMatch
    {
        // Trivia shares case, choice and diagnostics with the owning session,
        // but cannot recursively insert itself inside its own grammar.
        $this->pushLexeme();
        try {
            $wrappers = $this->triviaParsers ??= new WeakMap();
            $parser = $wrappers[$trivia] ??= $trivia->trivia();

            return $parser->recognizeInSession($source, $byteOffset, $this);
        } finally {
            $this->popLexeme();
        }
    }
}
