<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\ArgumentsTrait;

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
    const DEFAULT = null;

    /**
     * Parser to use as the default parser used when calling this Ruleset as a Parser.
     *
     * @var null|Parser
     */
    private $parser = null;

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
    protected $instanceRules = [];

    /**
     * Ruleset constructor, defining initial instance rules.
     *
     * @param string|array $name
     * @param null|string $definition
     */
    public function __construct($name = null, $definition = null)
    {
        if ($name !== null) {
            self::defineRule($this->instanceRules, $name, $definition);
        }
    }

    /**
     * Set a definition
     *
     * @param Definition[]|Parser[]|callable[] $rules
     * @param string $name
     * @param Definition|Parser|callable $definition
     * @throws \Exception
     */
    protected static function setRule(&$rules, $name, $definition)
    {
        if (isset($rules[$name])) {
            $rule = &$rules[$name];
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

                    case is_array($definition):
                        // S-C-S Array syntax
                        $rule->generator = self::getArgument($definition);
                        return;
                }
            }

            throw new \RuntimeException(sprintf('Cannot redefine `%2$s` using definition type `%1$s`', is_object($definition) ? get_class($definition) : gettype($definition), $name));
        }

        $rules[$name] = $definition;
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

    protected static function call(&$rules, $name, $arguments = [])
    {
        if (!isset($rules[$name])) {
            $rules[$name] = new Definition();
        }

        $rule = $rules[$name];

        switch (true) {
            case $rule instanceof Definition:
                // Parser Definition
                return new DefinitionInstance($rule, $arguments);

            case $rule instanceof Parser:
                // Parser
                return clone $rule;

            case is_callable($rule):
                // Generator function (should return Parser)
                $parser = $rule(...$arguments);
                if (!$parser instanceof Parser) {
                    throw new \InvalidArgumentException("Generator function for rule {$name} does not return Parser");
                }
                return $parser;

            case is_string($rule) && class_exists($rule) && is_subclass_of($rule, Parser::class):
                // Classname of a Parser class
                return new $rule(...$arguments);

            case is_string($rule) && isset($rules[$rule]):
                // Self-referential call
                return static::call($rules, $rule, $arguments);

            case is_array($rule):
                // S-C-S Array syntax
                return self::getArgument($rule);
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
        return static::call($this->instanceRules, $name, $arguments);
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

    private function initDefaulParser()
    {
        if ($this->parser === null) {
            if (!isset($this->instanceRules[self::DEFAULT])) {
                throw new \UnexpectedValueException('Missing default parser');
            }

            $this->parser = static::call($this->instanceRules, self::DEFAULT);
        }
    }

    /**
     * @return \vanderlee\comprehend\match\Match;
     */
    protected function parse(&$input, $offset, Context $context)
    {
        $this->initDefaulParser();

        if ($this->parser === null) {
            throw new \UnexpectedValueException('Missing parser');
        }

        $match = $this->parser->parse($input, $offset, $context);
        if ($match->match) {
            return $this->success($input, $offset, $match->length, $match);
        }
        return $this->failure($input, $offset, $match->length);
    }

    public function __toString()
    {
        $this->initDefaulParser();

        if ($this->parser === null) {
            throw new \UnexpectedValueException('Missing parser');
        }

        return (string)$this->parser;
    }
}
