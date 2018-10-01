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
     */
    public function __construct($key = null, $definition = null, $name = null)
    {
        if ($key !== null) {
            self::defineRule($this->instanceRules, $key, $definition);
        }

        self::$name = $name;
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
        if (isset($rules[$key])) {
            $rule = &$rules[$key];
            if ($rule instanceof Definition) {
                switch (true) {
                    case $definition instanceof Definition:
                        $rule->generator  = $definition->generator;
                        $rule->validators = $definition->validators;
                        return;

                    case $definition instanceof Parser:
                        $rule->generator = $definition;
                        return;

                    case is_callable($definition):
                        $rule->generator = $definition;
                        return;

                    case is_array($definition) || is_string($definition) || is_int($definition):
                        // S-C-S Array syntax
                        $rule->generator = self::getArgument($definition);
                        return;
                }

                throw new \RuntimeException(sprintf('Cannot redefine `%2$s` using definition type `%1$s`',
                    is_object($definition)
                        ? get_class($definition)
                        : gettype($definition), $key));
            }
        }

        $rules[$key] = $definition;
    }

    protected static function defineRule(&$rules, $names, $definition = null)
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
        }

        $rule = $rules[$key];

        switch (true) {
            // Parser Definition
            case $rule instanceof Definition:
                $instance = new Implementation($rule, $arguments);
                break;

            // Parser
            case $rule instanceof Parser:
                $instance = clone $rule;
                break;

            // Generator function (should return Parser)
            case is_callable($rule):
                $instance = $rule(...$arguments);
                if (!$instance instanceof Parser) {
                    throw new \InvalidArgumentException("Generator function for rule `{$key}` does not return Parser");
                }
                break;

            // Class name of a Parser class
            case is_string($rule) && class_exists($rule) && is_subclass_of($rule, Parser::class):
                $instance = new $rule(...$arguments);
                break;

            // Self-referential call
            case is_string($rule) && isset($rules[$rule]):
                $instance = static::call($rules, $rule, $arguments);
                break;

            // S-C-S Array syntax
            case is_array($rule) || is_string($rule) || is_int($rule):
                $instance = self::getArgument($rule);
                break;

            default:
                throw new \RuntimeException(sprintf('Cannot define `%2$s` using definition type `%1$s`',
                    is_object($rule)
                        ? get_class($rule)
                        : gettype($rule), $key));
        }

        if (!$instance->hasToken()) {
            $instance->token($key, static::$name);
        }

        return $instance;
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

    // Default parser

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
