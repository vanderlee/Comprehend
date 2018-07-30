<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\builder\DefinitionInstance;

/**
 * Shorthand for parser definitions
 *
 * @author Martijn
 */
class Definition {

	public $generator = null;
	public $validator = null;

	/**
	 * @param Parser|callable $generator Either a parser or a function returning a parser ('generator')
	 * @param callable $validator 
	 */	
	public function __construct($generator = null, callable $validator = null)
	{
		//@todo validate parser and validator
		
		$this->generator = $generator;
		$this->validator = $validator;
	}

	public function generator($parser = null)
	{
		$this->generator = $parser;

		return $this;
	}

	public function validator($validator = null)
	{
		$this->validator = $validator;

		return $this;
	}

	/**
	 * Build an instance of this parser definition.
	 * 
	 * @param Mixed[] $arguments
	 * @return DefinitionInstance
	 */
	public function build(...$arguments)
	{
		return new DefinitionInstance($this, $arguments);
	}

	/**
	 * Build an instance of this parser definition.
	 * Alias of `build()` method.
	 * 
	 * @param Mixed[] $arguments
	 * @return DefinitionInstance
	 */
	public function __invoke(...$arguments)
	{
		return $this->build(...$arguments);
	}

}
