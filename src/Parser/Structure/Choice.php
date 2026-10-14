<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Source\Source;
use InvalidArgumentException;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Match one of the provided parsers.
 *
 * @author Martijn
 */
class Choice extends IterableParser
{
    use PreferTrait;
    /**
     * @param mixed ...$arguments
     */
    public function __construct(Parser ...$arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('No arguments');
        }
        $this->parsers = $arguments;
    }
    /**
     * @param mixed ...$arguments
     *
     * @return self
     */
    public static function first(Parser ...$arguments): self
    {
        return (new self(...$arguments))->preferFirst();
    }
    /**
     * @param mixed ...$arguments
     *
     * @return self
     */
    public static function shortest(Parser ...$arguments): self
    {
        return (new self(...$arguments))->preferShortest();
    }
    /**
     * @param mixed ...$arguments
     *
     * @return self
     */
    public static function longest(Parser ...$arguments): self
    {
        return (new self(...$arguments))->preferLongest();
    }
    /**
     * @param Source $input
     * @param int $offset
     * @param ParseSession $context
     *
     * @return Failure|Success
     */
    private function parseFirst(Source $input, int $offset, ParseSession $context)
    {
        $max = 0;
        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $match = $parser->recognizeInSession($input, $offset, $context);
            if ($match instanceof Success) {
                return $this->success($input, $offset, $match->length, $match);
            }
            $max = max($max, $match->length);
        }
        return $this->failure($input, $offset, $max);
    }
    /**
     * Determine the longest and most successful match.
     *
     * @param AbstractMatch $attempt
     * @param AbstractMatch $match
     *
     * @return AbstractMatch
     */
    private static function determineLongestOf(AbstractMatch $attempt, AbstractMatch $match): AbstractMatch
    {
        if ($attempt->accepted === $match->accepted) {
            if ($attempt->length > $match->length) {
                return $attempt;
            }
            return $match;
        }
        if ($attempt instanceof Success) {
            return $attempt;
        }
        return $match;
    }
    /**
     * @param Source $input
     * @param int $offset
     * @param ParseSession $context
     *
     * @return Failure|Success
     */
    private function parseLongest(Source $input, int $offset, ParseSession $context)
    {
        $match = $this->failure($input, $offset);
        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $match = self::determineLongestOf($parser->recognizeInSession($input, $offset, $context), $match);
        }
        return $match instanceof Success ? $this->success($input, $offset, $match->length, $match) : $this->failure($input, $offset, $match->length);
    }
    /**
     * Determine the shortest and most successful match.
     *
     * @param AbstractMatch|null $attempt
     * @param AbstractMatch $match
     *
     * @return AbstractMatch
     */
    private static function determineShortestOf(AbstractMatch $attempt, AbstractMatch $match = null)
    {
        if ($match === null) {
            return $attempt;
        }
        if ($attempt instanceof Success && $match instanceof Failure) {
            return $attempt;
        }
        if ($attempt->accepted === $match->accepted && $attempt->length < $match->length) {
            return $attempt;
        }
        return $match;
    }
    /**
     * @param Source $input
     * @param int $offset
     * @param ParseSession $context
     *
     * @return Failure|Success
     */
    private function parseShortest(Source $input, int $offset, ParseSession $context)
    {
        /** @var AbstractMatch $match */
        $match = null;
        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $match = self::determineShortestOf($parser->recognizeInSession($input, $offset, $context), $match);
        }
        return $match instanceof Success ? $this->success($input, $offset, $match->length, $match) : $this->failure($input, $offset, $match->length);
    }
    /**
     * @param Source $input
     * @param int $offset
     * @param ParseSession $context
     *
     * @return Failure|Success
     */
    protected function recognize(Source $input, int $offset, ParseSession $context): AbstractMatch
    {
        $this->pushPreferenceToContext($context);
        try {
            switch ($context->getPreference()) {
                default:
                case Prefer::FIRST:
                    $match = $this->parseFirst($input, $offset, $context);
                    break;
                case Prefer::LONGEST:
                    $match = $this->parseLongest($input, $offset, $context);
                    break;
                case Prefer::SHORTEST:
                    $match = $this->parseShortest($input, $offset, $context);
                    break;
            }
        } finally {
            $this->popPreferenceFromContext($context);
        }
        return $match;
    }
    /**
     * Add one or more parsers as choices.
     *
     * @param Parser[] $arguments
     *
     * @return self
     */
    public function add(Parser ...$arguments): self
    {
        $this->parsers = array_merge($this->parsers, $arguments);
        return $this;
    }
    /**
     * @return string
     */
    public function __toString(): string
    {
        $prefix = $this->preference === Prefer::LONGEST ? 'longest-of' : ($this->preference === Prefer::SHORTEST ? 'shortest-of' : '');
        return $prefix . '( ' . implode(' | ', $this->parsers) . ' )';
    }
}
