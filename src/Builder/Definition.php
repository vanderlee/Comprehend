<?php

namespace Vanderlee\Comprehend\builder;

use Vanderlee\Comprehend\Parser\Parser;

/**
 * Shorthand for parser definitions
 *
 * @author Martijn
 */
class Definition
{

    public $generator  = null;
    public $validators = [];
    public $processors = [];

    /**
     * @param Parser|callable $generator Either a parser or a function returning a parser ('generator')
     * @param callable[] $validator
     */
    public function __construct($generator = null, $validator = null)
    {
        //@todo validate parser and validator

        $this->generator = $generator;
        if (is_callable($validator)) {
            $this->validators[] = $validator;
        }
    }

    public function setGenerator($parser)
    {
        $this->generator = $parser;

        return $this;
    }

    public function clearValidators()
    {
        $this->validators = [];

        return $this;
    }

    public function addValidator($validator)
    {
        $this->validators[] = $validator;

        return $this;
    }

    public function addProcessor($key, $processor)
    {
        $this->processors[$key] = $processor;

        return $this;
    }

    /**
     * Build an instance of this parser definition.
     *
     * @param Mixed[] $arguments
     * @return Implementation
     */
    public function build(...$arguments)
    {
        return new Implementation($this, $arguments);
    }

    /**
     * Build an instance of this parser definition.
     * Alias of `build()` method.
     *
     * @param Mixed[] $arguments
     * @return Implementation
     */
    public function __invoke(...$arguments)
    {
        return $this->build(...$arguments);
    }

}