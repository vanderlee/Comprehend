<?php

namespace vanderlee\comprehend\parser\structure;

use vanderlee\comprehend\core\ArgumentsTrait;
use vanderlee\comprehend\core\Context;
use vanderlee\comprehend\directive\Prefer;
use vanderlee\comprehend\match\Match;
use vanderlee\comprehend\parser\Parser;

/**
 * Match one of the provided parsers.
 *
 * @author Martijn
 */
class Choice extends IterableParser
{

    use ArgumentsTrait;
    use PreferTrait;

    public function __construct(...$arguments)
    {
        if (empty($arguments)) {
            throw new \InvalidArgumentException('No arguments');
        }

        $this->parsers = self::getArguments($arguments);
    }

    public static function first(...$arguments)
    {
        return (new self(...$arguments))->preferFirst();
    }

    public static function shortest(...$arguments)
    {
        return (new self(...$arguments))->preferShortest();
    }

    public static function longest(...$arguments)
    {
        return (new self(...$arguments))->preferLongest();
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->pushPreferenceToContext($context);

        switch ($context->getPreference()) {
            default:
            case Prefer::FIRST:
                $max = 0;
                foreach ($this->parsers as $parser) {
                    $match = $parser->parse($input, $offset, $context);
                    if ($match->match) {
                        $preferred_match = $this->success($input, $offset, $match->length, $match);
                        break 2;
                    }
                    $max = max($max, $match->length);
                }
                $preferred_match = $this->failure($input, $offset, $max);
                break;

            case Prefer::LONGEST:
                $max_match = $this->failure($input, $offset);
                foreach ($this->parsers as $parser) {
                    $match = $parser->parse($input, $offset, $context);
                    if ($match->match == $max_match->match) {
                        if ($match->length > $max_match->length) {
                            $max_match = $match->match
                                ? $this->success($input, $offset, $match->length, $match)
                                :
                                $this->failure($input, $offset, $match->length);
                        }
                    } elseif ($match->match) {
                        $max_match = $this->success($input, $offset, $match->length, $match);
                    }
                }
                $preferred_match = $max_match;
                break;

            case Prefer::SHORTEST:
                /** @var Match $match */
                $match = null;
                foreach ($this->parsers as $parser) {
                    $attempt = $parser->parse($input, $offset, $context);

                    switch (true) {
                        case!$match: // Keep attempt if first.
                        case $attempt->match && !$match->match: // Keep attempt if first match
                        case $attempt->match === $match->match && $attempt->length < $match->length: // Keep attempt if equally successful but shorter
                            $match = $attempt;
                    }
                }

                // This will fail! $match is not necessarily the shortest
                $preferred_match = $match->match
                    ? $this->success($input, $offset, $match->length, $match)
                    :
                    $this->failure($input, $offset, $match->length);
                break;
        }

        $this->popPreferenceFromContext($context);

        return $preferred_match;
    }

    /**
     * Add one or more parsers as choices
     *
     * @param string[]|int[]|Parser[] $arguments
     * @return $this
     */
    public function add(...$arguments)
    {
        $this->parsers = array_merge($this->parsers, self::getArguments($arguments));

        return $this;
    }

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
