<?php

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of Ruleset
 *
 * @method void define(array | string $name, Definition | Parser | callable $definition = [])
 * @method bool defined(string[] | string $name)
 * @method void undefine(string[] | string $name)
 * //method static void define(array|string $name, Definition|Parser|callable $definition = [])
 * //method static bool defined(string[]|string $name)
 * //method static void undefine(string[]|string $name)
 *
 * @author Martijn
 */
class Ruleset extends AbstractRuleset
{
    protected static function call(&$rules, $key, $arguments = [])
    {
        switch ($key) {
            case 'define':
                return self::defineRule($rules, ...$arguments);

            case 'defined':
                return self::isRuleDefined($rules, ...$arguments);

            case 'undefine':
                return self::undefineRule($rules, ...$arguments);
        }

        return parent::call($rules, $key, $arguments);
    }

    /**
     * Define an instance rule
     * @param string $name
     * @param Mixed $definition
     * @throws \Exception
     */
    public function __set($name, $definition)
    {
        self::setRule($this->instanceRules, $name, $definition);
    }

    /**
     * Undefine an instance rule
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->instanceRules[$name]);
    }

}
