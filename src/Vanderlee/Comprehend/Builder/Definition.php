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
	public $validate = null;
	public $inherit = null;
	public $output = null;

	/**
	 * 
	 * @param Parser|callable $parser Either a parser or a function returning a parser ('generator')
	 * @param type $validate
	 * @param type $inherit
	 * @param type $output
	 */
	public function __construct($parser = null, $validate = null, $inherit = null, $output = null)
	{
		$this->parser = $parser;
		$this->validate = $validate;
		$this->inherit = $inherit;
		$this->output = $output;
	}

	public function parser($parser = null)
	{
		$this->parser = $parser;

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
