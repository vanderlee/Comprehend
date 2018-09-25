<?php

namespace vanderlee\comprehend\match;

/**
 * Result of a failed parse
 *
 * @author Martijn
 */
class Failure extends Match
{

    public function __get($name)
    {
        return $name === 'match' ? false : parent::__get($name);
    }

    public function __toString()
    {
        return 'Failed match at ' . $this->length . ' characters';
    }

}
