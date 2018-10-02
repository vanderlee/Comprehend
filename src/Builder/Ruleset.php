<?php

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

/**
 * Description of Ruleset.
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
    /**
     * @param array      $rules
     * @param string     $key
     * @param array|null $arguments
     *
     * @throws \Exception
     *
     * @return bool|Implementation|Parser|void
     */
    protected static function call(&$rules, $key, $arguments = [])
    {
        switch ($key) {
            case 'define':
                self::defineRule($rules, $arguments[0], isset($arguments[1]) ? $arguments[1] : null);

                return;

            case 'defined':
                return self::isRuleDefined($rules, $arguments[0]);

            case 'undefine':
                self::undefineRule($rules, $arguments[0]);

                return;
        }

        return parent::call($rules, $key, $arguments);
    }

    /**
     * Define an instance rule.
     *
     * @param string $name
     * @param mixed  $definition
     *
     * @throws \Exception
     */
    public function __set($name, $definition)
    {
        self::setRule($this->instanceRules, $name, $definition);
    }

    /**
     * Undefine an instance rule.
     *
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->instanceRules[$name]);
    }
}
