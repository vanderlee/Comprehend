<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\ExactClassSnapshotReuseTrait;
use Vanderlee\Comprehend\Builder\ReusableSnapshot;
use Vanderlee\Comprehend\Builder\AnalyzableParser;
use Vanderlee\Comprehend\Builder\DefaultAnalysisTrait;
use Vanderlee\Comprehend\Builder\NullabilityLookup;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Selects one alternative using first, longest or shortest match policy. Reads scoped
 * preferences from ParseSession and retains only the selected attempt for result semantics.
 *
 * @author Martijn
 */
class Choice extends IterableParser implements AnalyzableParser, ReusableSnapshot
{
    use ExactClassSnapshotReuseTrait;
    use DefaultAnalysisTrait;
    use PreferTrait;

    protected function attemptAlternative(
        int|string $index,
        Parser $parser,
        Source $source,
        int $offset,
        ParseSession $session,
    ): AbstractMatch {
        return $parser->recognizeInSession($source, $offset, $session);
    }

    public function __construct(Parser ...$arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('No arguments');
        }
        $this->parsers = $arguments;
    }

    protected function copyConfigurationTo(Parser $copy): void
    {
        if (!$copy instanceof self) {
            throw new InvalidArgumentException('Choice configuration requires a Choice destination');
        }
        parent::copyConfigurationTo($copy);
        $copy->parsers = $this->parsers;
        $copy->preference = $this->preference;
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

    /** @return list<Parser> */
    public function analysisLeadingParsers(NullabilityLookup $lookup): array
    {
        return array_values($this->parsers);
    }

    public function analysisNullability(NullabilityLookup $lookup): ?bool
    {
        return $lookup->any(array_values($this->parsers));
    }

    public function __toString(): string
    {
        $prefix = match ($this->preference) {
            ChoicePolicy::LONGEST => 'longest-of',
            ChoicePolicy::SHORTEST => 'shortest-of',
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
                ChoicePolicy::LONGEST, ChoicePolicy::SHORTEST => $this->parseRanked(
                    $source,
                    $byteOffset,
                    $session,
                    $preference,
                ),
                default => $this->parseFirst($source, $byteOffset, $session),
            };
        } finally {
            $this->popPreferenceFromContext($session);
        }
    }

    private function parseRanked(
        Source $source,
        int $offset,
        ParseSession $session,
        ChoicePolicy $preference,
    ): AbstractMatch {
        $selected = null;
        foreach ($this->parsers as $index => $parser) {
            $attempt = $this->attemptAlternative($index, $parser, $source, $offset, $session);
            if ($selected === null || self::outranks($attempt, $selected, $preference)) {
                $selected = $attempt;
            }
        }

        return $selected instanceof Success
            ? $this->success($source, $offset, $selected->length, $selected)
            : $this->failure($source, $offset, $selected->length ?? 0);
    }

    private static function outranks(AbstractMatch $attempt, AbstractMatch $selected, ChoicePolicy $preference): bool
    {
        if ($attempt->accepted !== $selected->accepted) {
            return $attempt instanceof Success;
        }

        // Strict comparisons preserve the earlier alternative on equal lengths.
        // Failure progress follows this policy too; farthest diagnostics are separate.
        return $preference === ChoicePolicy::LONGEST
            ? $attempt->length > $selected->length
            : $attempt->length < $selected->length;
    }

    private function parseFirst(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $farthestProgress = 0;
        foreach ($this->parsers as $index => $parser) {
            $match = $this->attemptAlternative($index, $parser, $source, $offset, $session);
            if ($match instanceof Success) {
                return $this->success($source, $offset, $match->length, $match);
            }
            $farthestProgress = max($farthestProgress, $match->length);
        }

        return $this->failure($source, $offset, $farthestProgress);
    }
}
