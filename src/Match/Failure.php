<?php

namespace Vanderlee\Comprehend\Match;

/**
 * Result of a failed parse.
 *
 * @author Martijn
 */
class Failure extends AbstractMatch
{
    public function __get(string $name)
    {
        return $name === 'match'
            ? false
            : parent::__get($name);
    }

    public function __toString(): string
    {
        return 'Failed match at ' . $this->length . ' characters';
    }
}
