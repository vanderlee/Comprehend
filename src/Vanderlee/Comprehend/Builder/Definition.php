<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\builder\Factory;

/**
 * Shorthand for parser definitions
 *
 * @author Martijn
 */
class Definition {

	public $parser = null;
	public $validator = null;
//	public $inherit = null;
//	public $output = null;

	/**
	 * 
	 * @param Parser|callable $parser Either a parser or a function returning a parser ('generator')
	 * @param callable  $validator 
	 */
	
	public function __construct($parser = null, callable $validator = null)
	{
		$this->parser = $parser;
		$this->validator = $validator;
	}

	public function parser($parser = null)
	{
		$this->parser = $parser;

		return $this;
	}

	public function validator($validator = null)
	{
		$this->validator = $validator;

		return $this;
	}

	public function build(...$arguments)
	{
		return new Factory($this, $arguments);
	}

	public function __invoke(...$arguments)
	{
		return $this->build(...$arguments);
	}

}
