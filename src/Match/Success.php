<?php

namespace Vanderlee\Comprehend\Match;

use Vanderlee\Comprehend\Match\Output\CallbackTrait;
use Vanderlee\Comprehend\Match\Output\ResultTrait;
use Vanderlee\Comprehend\Match\Output\TokenTrait;

/**
 * Successful match of a parser
 *
 * @author Martijn
 */
class Success extends Match
{
    use TokenTrait, ResultTrait, CallbackTrait;

    /**
     * Boolean state indicating whether this match has been resolved already.
     * Each match may only be resolved once to prevent conflicts.
     *
     * @var bool
     */
    private $resolved = false;

    /**
     * Any successful matches tbat make up this success.
     *
     * @var Success[]
     */
    private $successes = [];

    /**
     * Create a new match
     * @param int $length
     * @param Success[]|Success $successes
     */
    public function __construct($length = 0, &$successes = [])
    {
        parent::__construct($length);

        $this->successes = $successes;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'match':
                return true;
            case 'results':
                $results = $this->getResults();
                unset($results[null]);
                return $results;
            case 'result':
                $results = $this->getResults();
                return isset($results[null])
                    ? $results[null]
                    : null;
            case 'token':
                return $this->getToken();
        }

        return parent::__get($name);
    }

    /**
     * Resolve any custom callbacks
     *
     * @return $this|Match
     * @throws \ErrorException
     */
    public function resolve()
    {
        if ($this->resolved) {
            throw new \ErrorException('Match already resolved');
        }
        $this->resolved = true;

        $this->getCallback();

        return $this;
    }

    public function __toString()
    {
        return 'Successfully matched ' . $this->length . ' characters';
    }

}
