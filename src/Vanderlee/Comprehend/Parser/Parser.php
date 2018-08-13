<?php

namespace vanderlee\comprehend\parser;

use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\match\Match;
use \vanderlee\comprehend\match\Success;
use \vanderlee\comprehend\match\Failure;

abstract class Parser
{

    use ResultTrait;
    use AssignTrait;

    /**
     * List of callbacks to call when this parser has matched a part of the
     * full parse.
     * @var type
     */
    private $callbacks = [];

    protected static function parseCharacter($character)
    {
        if ($character === '' || $character === null) {
            throw new \InvalidArgumentException('Empty argument');
        }

        if (is_int($character)) {
            return chr($character);
        } elseif (mb_strlen($character) > 1) {
            throw new \InvalidArgumentException('Non-character argument');
        }

        return $character;
    }

    /**
     * @return \vanderlee\comprehend\match\Match;
     */
    abstract protected function parse(&$input, $offset, Context $context);

    /**
     * @param string $input
     * @param integer $offset
     * @return Match;
     */
    public function match($input, $offset = 0)
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException("Negative offset");
        }

        return $this->parse($input, $offset, new Context())->resolve();
    }

    public function __invoke($input, $offset = 0)
    {
        return $this->match($input, $offset);
    }

    /**
     * Create a match
     *
     * @param bool $success
     * @param string $input
     * @param int $offset
     * @param int $length
     * @param Success[]|Success $successes
     * @return Match
     */
    protected function makeMatch($success, &$input, $offset, $length = 0, &$successes = [])
    {
        return $success
            ? $this->success($input, $offset, $length, $successes)
            : $this->failure($input, $offset);
    }

    /**
     * Create a new match as a copy from the specified match
     *
     * @param Match $match
     * @param string $input
     * @param int $offset
     * @return Match
     */
    protected function copyMatch(Match $match, &$input, $offset)
    {
        return $match->match
            ? $this->success($input, $offset, $match->length, $match)
            : $this->failure($input, $offset, $match->length);
    }

    /**
     * Create a succesful match
     *
     * @param string $input
     * @param int $offset
     * @param int $length
     * @param Success[]|Success $successes
     * @return Success
     */
    protected function success(&$input, $offset, $length = 0, &$successes = [])
    {
        $callbacks = $this->callbacks;

        $successes = is_array($successes) ? $successes : [$successes];

        return (new Success($length, $successes))
            ->addResultCallback(function (&$results) use ($input, $offset, $length, $callbacks) {
                $text = substr($input, $offset, $length);

                $this->resolveResultCallbacks($results, $text);
            })->addCustomCallback(function () use ($input, $offset, $length, $callbacks) {
                $text = substr($input, $offset, $length);

                $this->resolveAssignCallbacks($text);

                foreach ($callbacks as $callback) {
                    $callback($text, $input, $offset, $length);
                }
            });
    }

    /**
     * Create a failed match
     *
     * @param string $input
     * @param int $offset
     * @param int $length
     * @return Failure
     */
    protected function failure(&$input, $offset, $length = 0)
    {
        return new Failure($length);
    }

    public function callback(callable $callback)
    {
        $this->callbacks[] = $callback;
        return $this;
    }

    abstract public function __toString();
}
