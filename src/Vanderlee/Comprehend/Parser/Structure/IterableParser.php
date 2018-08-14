<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\core\ArgumentsTrait;
use \vanderlee\comprehend\parser\Parser;

/**
 * Abstract class indicating a parser that consists of multiple parsers and can be accessed as an array.
 *
 * @author Martijn
 */
abstract class IterableParser extends Parser implements \IteratorAggregate, \ArrayAccess
{
    use ArgumentsTrait;

    /**
     * @var Parser[] $parsers
     */
    protected $parsers = [];

    // implements IteratorAggregate

    public function getIterator()
    {
        return new \ArrayIterator($this->parsers);
    }

    // implements ArrayAccess

    public function offsetSet($offset, $value)
    {
        $value = self::getArgument($value);

        if (is_null($offset)) {
            $this->parsers[] = $value;
        } else {
            $this->parsers[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->parsers[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->parsers[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->parsers[$offset]) ? $this->parsers[$offset] : null;
    }
}
