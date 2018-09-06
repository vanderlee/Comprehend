<?php

namespace vanderlee\comprehend\parser;

use \vanderlee\comprehend\core\Context;
use vanderlee\comprehend\core\Token;
use \vanderlee\comprehend\match\Match;
use \vanderlee\comprehend\match\Success;
use \vanderlee\comprehend\match\Failure;

abstract class Parser
{

    use ResultTrait;
    use AssignTrait;
    use TokenTrait;

    /**
     * List of callbacks to call when this parser has matched a part of the full parse.
     *
     * @var callable[]
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
     * Match the input with this parser, starting from the offset position.
     *
     * @param $input
     * @param $offset
     * @param Context $context
     * @return \vanderlee\comprehend\match\Match
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
     * Create a successful match
     *
     * @param string $input
     * @param int $offset
     * @param int $length
     * @param Success[]|Success $successes
     * @return Success
     */
    protected function success(&$input, $offset, $length = 0, &$successes = [])
    {

        $successes = is_array($successes) ? $successes : [$successes];

        $success = new Success($length, $successes);

        // ResultTrait
        $success->addResultCallback(function (&$results) use ($input, $offset, $length) {
            $text = substr($input, $offset, $length);

            $this->resolveResultCallbacks($results, $text);
        });

        // AssignTrait
        $callbacks = $this->callbacks;
        $success->addCustomCallback(function () use ($input, $offset, $length, $callbacks) {
            $text = substr($input, $offset, $length);

            $this->resolveAssignCallbacks($text);

            foreach ($callbacks as $callback) {
                $callback($text, $input, $offset, $length);
            }
        });

        // TokenTrait
        $success->setTokenCallback(function (&$children) use ($input, $offset, $length) {
            return $this->resolveToken($input, $offset, $length, $children, get_class($this));
        });

        return $success;
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

    /**
     * Assign a function to be called if (and only if) this parser matched successfully as part of the whole syntax.
     *
     * @param callable $callback
     * @return $this
     */
    public function callback(callable $callback)
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    abstract public function __toString();
}
