<?php

namespace Vanderlee\Comprehend\Match;

use ErrorException;
use Vanderlee\Comprehend\Match\Output\CallbackTrait;
use Vanderlee\Comprehend\Match\Output\ResultTrait;
use Vanderlee\Comprehend\Match\Output\TokenTrait;

/**
 * Successful match of a parser.
 *
 * @author Martijn
 */
class Success extends AbstractMatch
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
    private $successes;
    private $coordinates;

    /** @internal Convert callback coordinates on the selected effect tree only. */
    public function useCoordinates(\Vanderlee\Comprehend\Compatibility\Coordinates $coordinates): void
    {
        if ($this->span === null || $this->span->source !== $coordinates->source) {
            throw new \InvalidArgumentException('Cannot convert an unbound or foreign-source match');
        }
        $coordinates->length($this->span->start, $this->span->end);
        foreach (array_merge($this->successes, $this->precedingMatches) as $child) {
            $child->useCoordinates($coordinates);
        }
        $this->coordinates = $coordinates;
    }

    /** @internal Raw text remains byte-based; only exposed numeric units change. */
    public function callbackCoordinates(int $offset, int $length): array
    {
        return $this->coordinates === null ? [$offset, $length]
            : [$this->coordinates->position($offset), $this->coordinates->length($offset, $offset + $length)];
    }

    /**
     * Create a new match.
     *
     * @param int $length
     * @param Success[]|Success $successes
     */
    public function __construct($length = 0, $successes = [])
    {
        parent::__construct($length);

        $this->successes = $successes;
    }

    public function __get(string $name)
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

                return $results[null]
                    ?? null;
            case 'token':
                return $this->getToken();
        }

        return parent::__get($name);
    }

    /**
     * Resolve any custom callbacks.
     *
     * @return $this|AbstractMatch
     * @throws ErrorException
     *
     */
    public function resolve(): AbstractMatch
    {
        if ($this->resolved) {
            throw new ErrorException('Match already resolved');
        }
        $this->resolved = true;

        $this->getCallback();

        return $this;
    }

    public function __toString(): string
    {
        return 'Successfully matched ' . $this->length . ' characters';
    }
}
