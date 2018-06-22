<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\core\ArgumentsTrait;

/**
 * Description of Ruleset
 *
 * @author Martijn
 */

class Ruleset {

	use ArgumentsTrait;

	/**
	 * @var Definition[] 
	 */
	private $definitions = [];

	public function define($name, Definition $definition)
	{		
		$this->definitions[$name] = $definition;
	}

	/**
	 * 
	 * @param type $name
	 * @param type $arguments
	 * @return Parser
	 * @throws Exception
	 */
	public function __call($name, $arguments)
	{
		if (isset($this->definitions[$name])) {
			return new Factory($this->definitions[$name], $arguments);
		}

		throw new \Exception("No parser named `{$name}` is defined");
	}
	
	/**
	 * Convert arguments such as strings and character codes to parsers.
	 * @param int[]|string[]|Parser[] $arguments
	 * @return Parser|Parser[]
	 */
	public static function toParser(...$arguments)
	{
		$parsers = self::getArguments($arguments);
		return count($parsers) === 1 ? reset($parsers) : $parsers;
	}

}