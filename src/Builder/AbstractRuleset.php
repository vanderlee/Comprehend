<?php

namespace Vanderlee\Comprehend\Builder;

use Exception;
use RuntimeException;
use UnexpectedValueException;
use Vanderlee\Comprehend\Core\ArgumentsTrait;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;

/**
 * @author Martijn
 */
abstract class AbstractRuleset extends Parser
{
    use ArgumentsTrait,
        RuleToParserTrait;

    /**
     * Constant to specify default parser for this ruleset, which is called when Ruleset is used as a Parser.
     */
    const ROOT = 'ROOT';

    /**
     * List of reserved parser names. These are used as methods instead.
     *
     * @var string[]
     */
    private static $reserved = [
        'define',
        'defined',
        'undefine',
    ];

    /**
     * Name of this ruleset. Intended for use with the standard library rulesets.
     *
     * @var string
     */
    protected static $name = null;

    /**
     * Parser to use as the default parser used when calling this Ruleset as a Parser.
     *
     * @var null|Parser
     */
    private $defaultParserCache = null;

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
     * @param             $definition Definition of the initial rule or `null` if `$key` is an array
     * @param string|null $name optional identifier for this ruleset
     *
     * @throws Exception
     */
    public function __construct($key = null, $definition = null, string $name = null)
    {
        if ($key !== null) {
            self::defineRule($this->instanceRules, $key, $definition);
        }

        self::$name = $name;
    }

    /**
     * Instantiate the default parser (if available).
     *
     * @return null|Implementation|Parser
     */
    private function getRootParser()
    {
        if ($this->defaultParserCache === null) {
            if (!isset($this->instanceRules[self::ROOT])) {
                throw new UnexpectedValueException('Missing default parser');
            }

            $this->defaultParserCache = static::call($this->instanceRules, self::ROOT);
        }

        return $this->defaultParserCache;
    }

    /**
     * @throws Exception
     */
    protected function parse(&$input, $offset, Context $context)
    {
        $match = $this->getRootParser()->parse($input, $offset, $context);
        if ($match instanceof Success) {
            return $this->success($input, $offset, $match->length, $match);
        }

        return $this->failure($input, $offset, $match->length);
    }

    /**
     * Set a definition.
     *
     * @param callable[]|Definition[]|Parser[] $rules
     * @param string $key
     * @param Definition|Parser|callable $definition
     *
     * @throws Exception
     */
    protected static function setRule(array &$rules, string $key, $definition)
    {
        if (isset($rules[$key])
            && $rules[$key] instanceof Definition) {
            try {
                $rules[$key]->define($definition);

                return;
            } catch (Exception $exception) {
                throw new RuntimeException(sprintf('Cannot redefine `%2$s` using definition type `%1$s`',
                    self::getArgumentType($definition), $key));
            }
        }

        $rules[$key] = $definition;
    }

    /**
     * Define a rule.
     *
     * @param array $rules
     * @param string|array $name
     * @param mixed|null $definition
     *
     * @throws Exception
     */
    protected static function defineRule(array &$rules, $name, $definition = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                self::defineRule($rules, $key, $value);
            }

            return;
        }

        if (in_array($name, self::$reserved)) {
            throw new RuntimeException('Cannot define reserved name `' . $name . '`');
        }

        self::setRule($rules, $name, $definition);
    }

    /**
     * Check if a specified name is defined in the rules map provided.
     *
     * @param $rules
     * @param $names
     *
     * @return bool
     */
    protected static function isRuleDefined($rules, $names)
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
     * Create a new instance of a definition.
     *
     * @param       $rules
     * @param       $key
     * @param array $arguments
     *
     * @return Implementation|Parser
     */
    protected static function call(&$rules, $key, array $arguments = [])
    {
        // Undefined rule; return empty definition implementation
        if (!isset($rules[$key])) {
            $rules[$key] = new Definition();

            return self::applyToken($key, new Implementation($rules[$key], $arguments));
        }

        // Rule reference
        if (is_string($rules[$key])
            && isset($rules[$rules[$key]])) {
            return self::applyToken($key, static::call($rules, $rules[$key], $arguments));
        }

        // Generic rule interpreters
        $instance = self::ruleToParser($rules[$key], $arguments);
        if ($instance) {
            return self::applyToken($key, $instance);
        }

        throw new RuntimeException(sprintf('Cannot instantiate `%2$s` using definition type `%1$s`',
            self::getArgumentType($rules[$key]), $key));
    }

    /**
     * Handle instance/object definitions.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return Parser
     * @throws Exception
     *
     */
    public function __call($name, $arguments = [])
    {
        return static::call($this->instanceRules, $name, $arguments);
    }

    /**
     * Handle static/class definitions.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return Parser
     * @throws Exception
     *
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
            return (string)$this->getRootParser();
        } catch (Exception $e) {
            // ignore
        }

        return '';
    }
}
