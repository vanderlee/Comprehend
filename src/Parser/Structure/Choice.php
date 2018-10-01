<?php

namespace Vanderlee\Comprehend\Parser\Structure;

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

    private function parseLongest(&$input, $offset, Context $context)
    {
        $max_match = $this->failure($input, $offset);
        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $match = $parser->parse($input, $offset, $context);
            if ($match->match === $max_match->match) {
                if ($match->length > $max_match->length) {
                    $max_match = ($match instanceof Success)
                        ? $this->success($input, $offset, $match->length, $match)
                        : $this->failure($input, $offset, $match->length);
                }
            } elseif ($match instanceof Success) {
                $max_match = $this->success($input, $offset, $match->length, $match);
            }
        }
        return $max_match;
    }

    private function parseShortest(&$input, $offset, Context $context)
    {
        /** @var Match $match */
        $match = null;
        /** @var Parser $parser */
        foreach ($this->parsers as $parser) {
            $attempt = $parser->parse($input, $offset, $context);

            switch (true) {
                case!$match: // Keep attempt if first.
                case ($attempt instanceof Success) && ($match instanceof Failure): // Keep attempt if first match
                case $attempt->match === $match->match && $attempt->length < $match->length: // Keep attempt if equally successful but shorter
                    $match = $attempt;
            }
        }

        // This will fail! $match is not necessarily the shortest
        return ($match instanceof Success)
            ? $this->success($input, $offset, $match->length, $match)
            : $this->failure($input, $offset, $match->length);
    }

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
