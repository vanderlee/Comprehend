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
	private $localDefinitions = [];

	/**
	 * @var Definition[]
	 */
	private static $globalDefinitions = [];

	/**
	 * Handle any type of definitions
	 * 
	 * @param array $definitions
	 * @param string $name
	 * @param array $arguments
	 * @return \vanderlee\comprehend\builder\Factory
	 * @throws \Exception
	 */
	private static function call(&$definitions, $name, $arguments)
	{
		switch ($name) {
			case 'define':
				$definitions[$arguments[0]] = $arguments[1];
				return null;

			case 'defined':
				return isset($definitions[$arguments[0]]);

			case 'undefine':
				unset($definitions[$arguments[0]]);
				return null;

			default:
				if (isset($definitions[$name])) {
					return new Factory($definitions[$name], $arguments);
				}
		}

		throw new \Exception("No parser named `{$name}` is defined");
	}

	/**
	 * Handle object definitions
	 * 
	 * @param string $name
	 * @param array $arguments
	 * @return Parser
	 * @throws Exception
	 * 
	 * @method $this define(string $name, Definition $definition) Add a parser definition to the object scope
	 * @method $this undefine(string $name) Remove a parser definition to the object scope
	 * @method bool defined(string $name) Check if a parser with the given name is defined in object scope
	 */
	public function __call($name, $arguments)
	{
		return self::call($this->localDefinitions, $name, $arguments) ?? $this;
	}

	/**
	 * Handle static definitions
	 * 
	 * @param string $name
	 * @param array $arguments
	 * @return Parser
	 * @throws Exception
	 * 
	 * @method static null define(string $name, Definition $definition) Add a parser definition to the static scope
	 * @method static null undefine(string $name) Remove a parser definition to the static scope
	 * @method static bool defined(string $name) Check if a parser with the given name is defined in the static scope
	 */
	public static function __callStatic($name, $arguments)
	{
		return self::call(self::$globalDefinitions, $name, $arguments);
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
