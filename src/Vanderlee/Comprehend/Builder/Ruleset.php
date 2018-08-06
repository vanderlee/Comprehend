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
 * //method static void define(array|string $name, Definition|Parser|callable $definition = [])
 * //method static bool defined(string[]|string $name)
 * //method static void undefine(string[]|string $name)
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
	private static function setRule(&$rules, $name, $definition)
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

			throw new \RuntimeException("Cannot redefine `{$name}`");
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
			throw new \RuntimeException("Cannot define reserved name `{$names}`");
		}

		self::setRule($rules, $names, $definition);
	}

    /**
     * Check if a specified name is defined in the rules map provided
     *
     * @param $rules
     * @param $names
     * @return bool
     */
    private static function isRuleDefined(&$rules, $names)
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
                return self::defineRule($rules, ...$arguments);

            case 'defined':
                return self::isRuleDefined($rules, ...$arguments);

            case 'undefine':
                return self::undefineRule($rules, ...$arguments);
        }

        if (!isset($rules[$name])) {
            $rules[$name] = new Definition();
        }

        $rule = $rules[$name];

        switch (true) {
            case $rule instanceof Definition:
                return new DefinitionInstance($rule, $arguments);

            case $rule instanceof Parser:
                return clone $rule;

            case is_callable($rule):
                return $rule(...$arguments);

            case is_string($rule) && class_exists($rule):
                return new $rule(...$arguments);
        }

        throw new \InvalidArgumentException(sprintf('Invalid definition type `%1$s`', is_object($rule) ? get_class($rule) : gettype($rule)));
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
		self::setRule($this->instanceRules, $name, $definition);
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
