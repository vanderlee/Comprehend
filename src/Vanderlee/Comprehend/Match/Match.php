<?php

namespace vanderlee\comprehend\match;

/**
 * Description of ParserToken
 *
 * @author Martijn
 * 
 * @property-read int $length Length of the match
 * @property-read array $results List of output results
 * @property-read string|null $result Default output result
 */
abstract class Match {

	private $length;
	
	/**
	 * @param string $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name) {
		switch($name) {
			case 'length':
				return $this->length;
			case 'results':
				return [];
			case 'result':
				return  null;
		}
		
		throw new \Exception("Property name `{$name}` not recognized");
	}

	/**
	 * Create a new match
	 * @param int $length
	 */
	public function __construct(int $length)
	{
		$this->length = $length;
	}
	
	/**
	 * Resolve any match stuff (should only ever be called from AbstractParser!
	 * Not for human consumption
	 * 
	 * Chainable
	 * 
	 * @return $this
	 */
	public function resolve() {
		return $this;
	}
		
	/**
	 * Return the result for the name specified or the default value if not set.
	 * 
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public function getResult($name, $default = null) {
		return $default;
	}
	
	/**
	 * Return whether there is a result for the name specified.
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function hasResult($name) {
		return false;
	}

}
