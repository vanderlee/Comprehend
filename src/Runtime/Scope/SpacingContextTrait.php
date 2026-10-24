<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime\Scope;

use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;
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

    /** @internal Whether repetition must recognize intervening trivia. */
    public function hasActiveSpacing(): bool
    {
        return $this->lexemeDepth <= 0
            && end($this->spacers) instanceof Parser;
    }

    /** Zero means no spacing was consumed; false means the active trivia parser rejected input. */
    public function skipSpacing(Source $source, int $offset, ?Success &$match = null): int|false
    {
        $match = null;

        $trivia = end($this->spacers);

        if (
            $this->lexemeDepth > 0
            || !$trivia instanceof Parser
        ) {
            return 0;
        }

        $attempt = $this->recognizeTrivia($trivia, $source, $offset);
        if (!$attempt instanceof Success) {
            return false;
        }

        $match = $attempt;

        return $match->length;
    }

    /** @internal Built-in prepared combinators can retain plain trivia syntax without an execution record. */
    public function skipSpacingSyntax(Source $source, int $offset, Success|ConcreteNode|null &$syntax = null): int|false
    {
        $syntax = null;

        $trivia = end($this->spacers);

        if (
            $this->lexemeDepth > 0
            || !$trivia instanceof Parser
        ) {
            return 0;
        }

        // Keep the prepared loop's hot path here rather than forwarding through
        // the match-returning path. Both paths share the same cached wrapper.
        // Scope bookkeeping stays local: this final session owns the private depth counter.
        ++$this->lexemeDepth;
        try {
            $wrappers = $this->triviaParsers ??= new WeakMap();
            $parser = $wrappers[$trivia] ??= $trivia->trivia();
            $syntax = $parser->tryConcreteSyntax($source, $offset, $this);
            if ($syntax !== null) {
                return $syntax->span->end - $syntax->span->start;
            }
            $attempt = $parser->recognizeInSession($source, $offset, $this);
        } finally {
            --$this->lexemeDepth;
        }
        if (!$attempt instanceof Success) {
            return false;
        }
        $syntax = $attempt;

        return $attempt->length;
    }

    private function recognizeTrivia(
        Parser $trivia,
        Source $source,
        int $byteOffset,
    ): AbstractMatch {
        // Trivia shares case, choice and diagnostics with the owning session,
        // but cannot recursively insert itself inside its own grammar.
        ++$this->lexemeDepth;
        try {
            $wrappers = $this->triviaParsers ??= new WeakMap();
            $parser = $wrappers[$trivia] ??= $trivia->trivia();

            return $parser->recognizeInSession($source, $byteOffset, $this);
        } finally {
            --$this->lexemeDepth;
        }
    }
}
