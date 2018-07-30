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
 * @todo Mass-definition using "define"?
 *		Do old call/callstatic
 *
 * @author Martijn
 */
class Ruleset {

	use ArgumentsTrait;

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

	/**
	 * @param type $name
	 * @param type $definition
	 */
	public function __set($name, $definition)
	{
		self::set($this->instanceRules, $name, $definition);
	}

	/**
	 * Handle object definitions
	 * 
	 * @param string $name
	 * @param array $arguments
	 * @return Parser
	 * @throws Exception
	 */
	public function __call($name, $arguments = [])
	{
		if (!isset($this->instanceRules[$name])) {
			$this->instanceRules[$name] = new Definition();
		}

		switch (true) {
			case $this->instanceRules[$name] instanceof Definition:
				return new DefinitionInstance($this->instanceRules[$name], $arguments);
			case $this->instanceRules[$name] instanceof Parser:
				return clone $this->instanceRules[$name];
			case is_callable($this->instanceRules[$name]):
				return $this->instanceRules[$name](...$arguments);
		}
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

	/**
	 * Define one or more static rules
	 * 
	 * @param string|array $name
	 * @param Definition|Parser|callable $definition
	 */
	public static function define($name, $definition = null)
	{
		if (is_array($name)) {
			foreach ($name as $key => $value) {
				$this->define($key, $value);
			}
			return;
		}

		self::set(self::$staticRules, $name, $definition);
	}

	/**
	 * Remove definitions of static rules
	 * 
	 * @param string[] $names
	 */
	public static function undefine(...$names)
	{
		foreach ($name as $key => $value) {
			unset(self::$staticRules[$name]);
		}
	}
	
	/**
	 * Are the names defined as static rules?
	 * 
	 * @param string[] $names
	 * @return boolean
	 */
	public static function defined(...$names) {
		foreach ($name as $key => $value) {
			if (!isset(self::$staticRules[$name])) {
				return false;
			}
		}
		
		return true;
	}

}
