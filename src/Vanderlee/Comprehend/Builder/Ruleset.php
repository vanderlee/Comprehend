<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\ArgumentsTrait;
use \vanderlee\comprehend\parser\Stub;
use \vanderlee\comprehend\builder\Definition;

/**
 * Description of Ruleset
 * 
 * @todo Constructor with mass definition
 *
 * @method void define(array|string $name, Definition|Parser|callable $definition = [])
 * @method bool defined(string[]|string $name)
 * @method void undefine(string[]|string $name)
 * 
 * @author Martijn
 */
class Ruleset {

	use ArgumentsTrait;

	/**
	 * List of reserved parser names. These are used as methods instead.
	 * 
	 * @var type 
	 */
	private static $reserved = [
		'define',
		'defined',
		'undefine'
	];

	/**
	 * @var Definition[]|Parser[]|callable[]
	 */
	private static $staticRules = [];

	/**
	 * @var Definition[]|Parser[]|callable[]
	 */
	private $instanceRules = [];

	/**
	 * Set a definition
	 * 
	 * @param Definition[]|Parser[]|callable[] $rules
	 * @param string $name
	 * @param Definition|Parser|callable $definition
	 * @throws \Exception
	 */
	private static function set(&$rules, $name, $definition)
	{
		if (isset($rules[$name])) {
			if ($rules[$name] instanceof Definition) {
				switch (true) {
					case $definition instanceof Definition:
						$rules[$name]->generator = $definition->generator;
						$rules[$name]->validator = $definition->validator;
						return;
					case $definition instanceof Parser:
						$rules[$name]->generator = $definition;
						return;
					case is_callable($definition):
						$rules[$name]->generator = $definition;
						return;
				}
			}

			throw new \Exception("Cannot redefine `{$name}`");
		}

		$rules[$name] = $definition;
	}

	private static function defineRule(&$rules, $names, $definition = null)
	{
		if (is_array($names)) {
			foreach ($names as $key => $value) {
				self::defineRule($rules, $key, $value);
			}
			return;
		}

		if (in_array($names, self::$reserved)) {
			throw new \Exception("Cannot define reserved name `{$names}`");
		}

		self::set($rules, $names, $definition);
	}

	private static function isRuleDefined(&$rules, $names): bool
	{
		foreach ((array) $names as $key) {
			if (!isset($rules[$key])) {
				return false;
			}
		}

		return true;
	}

	private static function undefineRule(&$rules, $names)
	{
		foreach ((array) $names as $key) {
			unset($rules[$key]);
		}
	}

	private static function call(&$rules, $name, $arguments = [])
	{
		switch ($name) {
			case 'define':
				return self::defineRule($rules, $arguments[0], $arguments[1]);

			case 'defined':
				return self::isRuleDefined($rules, $arguments[0]);

			case 'undefine':
				return self::undefineRule($rules, $arguments[0]);

			default:
				if (!isset($rules[$name])) {
					$rules[$name] = new Definition();
				}

				switch (true) {
					case $rules[$name] instanceof Definition:
						return new DefinitionInstance($rules[$name], $arguments);
					case $rules[$name] instanceof Parser:
						return clone $rules[$name];
					case is_callable($rules[$name]):
						return $rules[$name](...$arguments);
				}
		}
	}

	/**
	 * Handle instance/object definitions
	 * 
	 * @param string $name
	 * @param array $arguments
	 * @return Parser
	 * @throws Exception
	 */
	public function __call($name, $arguments = [])
	{
		return self::call($this->instanceRules, $name, $arguments);
	}

	/**
	 * Handle static/class definitions
	 * 
	 * @param string $name
	 * @param array $arguments
	 * @return Parser
	 * @throws Exception
	 */
	public static function __callStatic($name, $arguments = [])
	{
		return self::call(self::$staticRules, $name, $arguments);
	}

	/**
	 * @param type $name
	 * @param type $definition
	 */
	public function __set($name, $definition)
	{
		self::set($this->instanceRules, $name, $definition);
	}

	public function __get($name)
	{
		return $this->__call($name);
	}

	public function __isset($name)
	{
		return isset($this->instanceRules[$name]);
	}

	public function __unset($name)
	{
		unset($this->instanceRules[$name]);
	}

	//@todo tools

	/**
	 * @TODO rename to something more logical, or move out entirely?
	 * Convert arguments such as strings and character codes to parsers.
	 * 
	 * @param int[]|string[]|Parser[] $arguments
	 * @return Parser|Parser[]
	 */
	public static function toParser(...$arguments)
	{
		$parsers = self::getArguments($arguments);
		return count($parsers) === 1 ? reset($parsers) : $parsers;
	}

}
