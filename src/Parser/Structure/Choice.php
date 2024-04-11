<?php

namespace Vanderlee\Comprehend\Parser\Structure;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
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
     * @return self
     */
    public static function first(...$arguments): self
    {
        return (new self(...$arguments))->preferFirst();
    }

    /**
     * @param mixed ...$arguments
     *
     * @return self
     */
    public static function shortest(...$arguments): self
    {
        return (new self(...$arguments))->preferShortest();
    }

    /**
     * @param mixed ...$arguments
     *
     * @return self
     */
    public static function longest(...$arguments): self
    {
        return (new self(...$arguments))->preferLongest();
    }

    /**
     * @param string $input
     * @param int $offset
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
     * Determine the longest and most successful match.
     *
     * @param AbstractMatch $attempt
     * @param AbstractMatch $match
     *
     * @return AbstractMatch
     */
    private static function determineLongestOf(AbstractMatch $attempt, AbstractMatch $match): AbstractMatch
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
     * @param string $input
     * @param int $offset
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
     * @param string $input
     * @param int $offset
     * @param Context $context
     *
     * @return Failure|Success
     */
    private function parseShortest(&$input, $offset, Context $context)
    {
        /** @var AbstractMatch $match */
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
     * @param string $input
     * @param int $offset
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
     * Add one or more parsers as choices.
     *
     * @param string[]|int[]|Parser[] $arguments
     *
     * @return self
     */
    public function add(...$arguments): self
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

        return $prefix . '( ' . implode(' | ', $this->parsers) . ' )';
    }
}
