<?php

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of abstract Ruleset
 *
 * @author Martijn
 */
abstract class AbstractRuleset extends Parser
{
    use ArgumentsTrait;

    /**
     * Constant to specify default parser for this ruleset, which is called when Ruleset is used as a Parser.
     */
    const ROOT = 'ROOT';

    /**
     * List of static methods (in order) to attempt instantiating a rule
     *
     * @var string[]
     */
    private static $ruleMethods = [
        'ruleToDefinition',
        'ruleToParser',
        'ruleToCallable',
        'ruleToClassname',
        'ruleToReference',
        'ruleToArgument',
    ];

    /**
     * List of reserved parser names. These are used as methods instead.
     *
     * @var string[]
     */
    private static $reserved = [
        'define',
        'defined',
        'undefine'
    ];

    /**
     * Name of this ruleset. Intended for use with the standard library rulesets
     *
     * @var string
     */
    protected static $name = null;

    /**
     * Parser to use as the default parser used when calling this Ruleset as a Parser.
     *
     * @var null|Parser
     */
    private $parser = null;

    /**
     * @var Definition[]|Parser[]|callable[]
     */
    private static $staticRules = [];

    /**
     * @var Definition[]|Parser[]|callable[]
     */
    protected $instanceRules = [];

    /**
     * Ruleset constructor, defining initial instance rules.
     *
     * @param string|array $key Either a key of an initial rule to define or a [ key : definition ] array
     * @param null|string $definition Definition of the initial rule or `null` if `$key` is an array
     * @param null|string $name optional identifier for this ruleset
     * @throws \Exception
     */
    public function __construct($key = null, $definition = null, $name = null)
    {
        if ($key !== null) {
            self::defineRule($this->instanceRules, $key, $definition);
        }

        self::$name = $name;
    }

    /**
     * Instantiate the default parser (if available)
     */
    private function initDefaultParser()
    {
        if ($this->parser === null) {
            if (!isset($this->instanceRules[self::ROOT])) {
                throw new \UnexpectedValueException('Missing default parser');
            }

            $this->parser = static::call($this->instanceRules, self::ROOT);
        }
    }

    protected function parse(&$input, $offset, Context $context)
    {
        $this->initDefaultParser();

        $match = $this->parser->parse($input, $offset, $context);
        if ($match instanceof Success) {
            return $this->success($input, $offset, $match->length, $match);
        }
        return $this->failure($input, $offset, $match->length);
    }

    /**
     * Set a definition
     *
     * @param Definition[]|Parser[]|callable[] $rules
     * @param string $key
     * @param Definition|Parser|callable $definition
     * @throws \Exception
     */
    protected static function setRule(&$rules, $key, $definition)
    {
        if (isset($rules[$key])
            && $rules[$key] instanceof Definition) {

            if ($definition instanceof Definition) {
                $rules[$key]->generator  = $definition->generator;
                $rules[$key]->validators = $definition->validators;
                return;
            }

            if ($definition instanceof Parser) {
                $rules[$key]->generator = $definition;
                return;
            }

            if (is_callable($definition)) {
                $rules[$key]->generator = $definition;
                return;
            }

            if (is_array($definition)
                || is_string($definition)
                || is_int($definition)) {
                $rules[$key]->generator = self::getArgument($definition);
                return;
            }

            throw new \RuntimeException(sprintf('Cannot redefine `%2$s` using definition type `%1$s`',
                is_object($definition)
                    ? get_class($definition)
                    : gettype($definition), $key));
        }

        $rules[$key] = $definition;
    }

    /**
     * Define a rule
     *
     * @param array $rules
     * @param string|array $name
     * @param Mixed|null $definition
     * @throws \Exception
     */
    protected static function defineRule(&$rules, $name, $definition = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                self::defineRule($rules, $key, $value);
            }
            return;
        }

        if (in_array($name, self::$reserved)) {
            throw new \RuntimeException("Cannot define reserved name `{$name}`");
        }

        self::setRule($rules, $name, $definition);
    }

    /**
     * Check if a specified name is defined in the rules map provided
     *
     * @param $rules
     * @param $names
     * @return bool
     */
    protected static function isRuleDefined(&$rules, $names)
    {
        foreach ((array)$names as $key) {
            if (!isset($rules[$key])) {
                return false;
            }
        }

        return true;
    }

    protected static function undefineRule(&$rules, $names)
    {
        foreach ((array)$names as $key) {
            unset($rules[$key]);
        }
    }

    private static function applyToken($key, Parser $parser)
    {
        if (!$parser->hasToken()) {
            $parser->token($key, static::$name);
        }

        return $parser;
    }

    /**
     * @param array $rules
     * @param string $key
     * @param array $arguments
     * @return Implementation
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function ruleToDefinition(&$rules, $key, $arguments)
    {
        if ($rules[$key] instanceof Definition) {
            return new Implementation($rules[$key], $arguments);
        }

        return null;
    }

    /**
     * @param array $rules
     * @param string $key
     * @param array $arguments
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function ruleToParser(&$rules, $key)
    {
        if ($rules[$key] instanceof Parser) {
            return clone $rules[$key];
        }

        return null;
    }

    /**
     * @param array $rules
     * @param string $key
     * @param array $arguments
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function ruleToCallable(&$rules, $key, $arguments)
    {
        if (is_callable($rules[$key])) {
            $instance = $rules[$key](...$arguments);
            if ($instance instanceof Parser) {
                return $instance;
            }

            throw new \InvalidArgumentException("Generator function for `{$key}` does not return Parser");
        }

        return null;
    }

    /**
     * @param array $rules
     * @param string $key
     * @param array $arguments
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function ruleToClassname(&$rules, $key, $arguments)
    {
        if (is_string($rules[$key])
            && class_exists($rules[$key])
            && is_subclass_of($rules[$key], Parser::class)) {
            return new $rules[$key](...$arguments);
        }

        return null;
    }

    /**
     * @param array $rules
     * @param string $key
     * @param array $arguments
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function ruleToReference(&$rules, $key, $arguments)
    {
        if (is_string($rules[$key])
            && isset($rules[$rules[$key]])) {
            return static::call($rules, $rules[$key], $arguments);
        }

        return null;
    }

    /**
     * @param array $rules
     * @param string $key
     * @param array $arguments
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function ruleToArgument(&$rules, $key)
    {
        if (is_array($rules[$key])
            || is_string($rules[$key])
            || is_int($rules[$key])) {
            return self::getArgument($rules[$key]);
        }

        return null;
    }

    /**
     * Create a new instance of a definition
     *
     * @param $rules
     * @param $key
     * @param array $arguments
     * @return Implementation|Parser
     */
    protected static function call(&$rules, $key, $arguments = [])
    {
        if (!isset($rules[$key])) {
            $rules[$key] = new Definition();
            self::applyToken($key, self::ruleToDefinition($rules, $key, $arguments));
        }

        foreach (self::$ruleMethods as $ruleMethod) {
            $instance = self::$ruleMethod($rules, $key, $arguments);
            if ($instance) {
                return self::applyToken($key, $instance);
            }
        }

        throw new \RuntimeException(sprintf('Cannot instantiate `%2$s` using definition type `%1$s`',
            is_object($rules[$key])
                ? get_class($rules[$key])
                : gettype($rules[$key]), $key));
    }

    /**
     * Handle instance/object definitions
     *
     * @param string $name
     * @param array $arguments
     * @return Parser
     * @throws \Exception
     */
    public function __call($name, $arguments = [])
    {
        return static::call($this->instanceRules, $name, $arguments);
    }

    /**
     * Handle static/class definitions
     *
     * @param string $name
     * @param array $arguments
     * @return Parser
     * @throws \Exception
     */
    public static function __callStatic($name, $arguments = [])
    {
        return static::call(self::$staticRules, $name, $arguments);
    }

    public function __get($name)
    {
        return static::call($this->instanceRules, $name);
    }

    public function __isset($name)
    {
        return isset($this->instanceRules[$name]);
    }

    public function __toString()
    {
        try {
            $this->initDefaultParser();
        } catch (\Exception $e) {
            // ignore
        }

        return (string)$this->parser;
    }
}
