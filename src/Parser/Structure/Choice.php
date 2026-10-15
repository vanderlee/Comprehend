<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Selects one alternative using first, longest or shortest match policy. Reads scoped
 * preferences from ParseSession and retains only the selected attempt for result semantics.
 *
 * @author Martijn
 */
class Choice extends IterableParser
{
    use PreferTrait;

    public function __construct(Parser ...$arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('No arguments');
        }
        $this->parsers = $arguments;
    }

    public static function first(Parser ...$arguments): self
    {
        return (new self(...$arguments))->preferFirst();
    }

    /** @internal Explicit policies, metadata and subclasses retain their boundary. */
    public function canFlatten(): bool
    {
        return static::class === self::class && !$this->hasCompositionMetadata() && $this->preference === null;
    }

    public static function shortest(Parser ...$arguments): self
    {
        return (new self(...$arguments))->preferShortest();
    }

    public static function longest(Parser ...$arguments): self
    {
        return (new self(...$arguments))->preferLongest();
    }

    /**
     * Add one or more parsers as choices.
     *
     * @param Parser ...$arguments
     *
     * @return self
     */
    public function add(Parser ...$arguments): self
    {
        $this->parsers = array_merge($this->parsers, $arguments);

        return $this;
    }

    public function __toString(): string
    {
        $prefix = match ($this->preference) {
            Prefer::LONGEST => 'longest-of',
            Prefer::SHORTEST => 'shortest-of',
            default => '',
        };

        return $prefix . '( ' . implode(' | ', $this->parsers) . ' )';
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $this->pushPreferenceToContext($session);
        try {
            $preference = $session->getPreference();

            return match ($preference) {
                Prefer::LONGEST, Prefer::SHORTEST => $this->parseRanked($source, $byteOffset, $session, $preference),
                default => $this->parseFirst($source, $byteOffset, $session),
            };
        } finally {
            $this->popPreferenceFromContext($session);
        }
    }

    private function parseRanked(Source $source, int $offset, ParseSession $session, string $preference): AbstractMatch
    {
        $selected = null;
        foreach ($this->parsers as $parser) {
            $attempt = $parser->recognizeInSession($source, $offset, $session);
            if ($selected === null || self::outranks($attempt, $selected, $preference)) {
                $selected = $attempt;
            }
        }

        return $selected instanceof Success
            ? $this->success($source, $offset, $selected->length, $selected)
            : $this->failure($source, $offset, $selected->length ?? 0);
    }

    private static function outranks(AbstractMatch $attempt, AbstractMatch $selected, string $preference): bool
    {
        if ($attempt->accepted !== $selected->accepted) {
            return $attempt instanceof Success;
        }

        // Strict comparisons preserve the earlier alternative on equal lengths.
        // Failure progress follows this policy too; farthest diagnostics are separate.
        return $preference === Prefer::LONGEST
            ? $attempt->length > $selected->length
            : $attempt->length < $selected->length;
    }

    private function parseFirst(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $farthestProgress = 0;
        foreach ($this->parsers as $parser) {
            $match = $parser->recognizeInSession($source, $offset, $session);
            if ($match instanceof Success) {
                return $this->success($source, $offset, $match->length, $match);
            }
            $farthestProgress = max($farthestProgress, $match->length);
        }

        return $this->failure($source, $offset, $farthestProgress);
    }
}
