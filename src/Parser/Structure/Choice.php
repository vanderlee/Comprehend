<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Match;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Match one of the provided parsers.
 *
 * @author Martijn
 */
class Choice extends IterableParser
{
    use ArgumentsTrait,
        PreferTrait;

    /**
     * @param mixed ...$arguments
     */
    public function __construct(...$arguments)
    {
        if (empty($arguments)) {
            throw new InvalidArgumentException('No arguments');
        }

        $this->parsers = self::getArguments($arguments);
    }

    /**
     * @param mixed ...$arguments
     *
     * @return Choice
     */
    public static function first(...$arguments)
    {
        return (new self(...$arguments))->preferFirst();
    }

    /**
     * @param mixed ...$arguments
     *
     * @return Choice
     */
    public static function shortest(...$arguments)
    {
        return (new self(...$arguments))->preferShortest();
    }

    /**
     * @param mixed ...$arguments
     *
     * @return Choice
     */
    public static function longest(...$arguments)
    {
        return (new self(...$arguments))->preferLongest();
    }

    /**
     * @param string  $input
     * @param int     $offset
     * @param Context $context
     *
     * @return Failure|Success
     */
    private function parseFirst(&$input, $offset, Context $context)
    {
        $max = 0;
        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $match = $parser->parse($input, $offset, $context);
            if ($match instanceof Success) {
                return $this->success($input, $offset, $match->length, $match);
            }
            $max = max($max, $match->length);
        }
        return $this->failure($input, $offset, $max);
    }

    /**
     * Determine the longest and most successful match
     *
     * @param Match $attempt
     * @param Match $match
     *
     * @return Match
     */
    private static function determineLongestOf(Match $attempt, Match $match)
    {
        if ($attempt->match === $match->match) {
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
     * @param string  $input
     * @param int     $offset
     * @param Context $context
     *
     * @return Failure|Success
     */
    private function parseLongest(&$input, $offset, Context $context)
    {
        $match = $this->failure($input, $offset);

        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $match = self::determineLongestOf($parser->parse($input, $offset, $context), $match);
        }

        return ($match instanceof Success)
            ? $this->success($input, $offset, $match->length, $match)
            : $this->failure($input, $offset, $match->length);
    }

    /**
     * Determine the shortest and most successful match
     *
     * @param Match|null $attempt
     * @param Match      $match
     *
     * @return Match
     */
    private static function determineShortestOf(Match $attempt, Match $match = null)
    {
        if ($match === null) {
            return $attempt;
        }

        if ($attempt instanceof Success
            && $match instanceof Failure) {
            return $attempt;
        }

        if ($attempt->match === $match->match
            && $attempt->length < $match->length) {
            return $attempt;
        }

        return $match;
    }

    /**
     * @param string  $input
     * @param int     $offset
     * @param Context $context
     *
     * @return Failure|Success
     */
    private function parseShortest(&$input, $offset, Context $context)
    {
        /** @var Match $match */
        $match = null;

        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $match = self::determineShortestOf($parser->parse($input, $offset, $context), $match);
        }

        return ($match instanceof Success)
            ? $this->success($input, $offset, $match->length, $match)
            : $this->failure($input, $offset, $match->length);
    }

    /**
     * @param string  $input
     * @param int     $offset
     * @param Context $context
     *
     * @return Failure|Success
     */
    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushPreferenceToContext($context);

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

        $this->popPreferenceFromContext($context);

        return $match;
    }

    /**
     * Add one or more parsers as choices
     *
     * @param string[]|int[]|Parser[] $arguments
     *
     * @return $this
     */
    public function add(...$arguments)
    {
        $this->parsers = array_merge($this->parsers, self::getArguments($arguments));

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $prefix = $this->preference === Prefer::LONGEST
            ? 'longest-of'
            :
            ($this->preference === Prefer::SHORTEST
                ? 'shortest-of'
                :
                '');

        return $prefix . '( ' . join(' | ', $this->parsers) . ' )';
    }
}
