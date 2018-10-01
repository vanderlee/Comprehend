<?php

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;

trait RuleToParserTrait
{
    /**
     * List of static methods (in order) to attempt instantiating a rule
     *
     * @var string[]
     */
    private static $ruleToParserMethods = [
        'definitionRuleToParser',
        'parserRuleToParser',
        'callableRuleToParser',
        'classnameRuleToParser',
        'argumentRuleToParser',
    ];

    /**
     * @param mixed $rule
     * @param array $arguments
     * @return Implementation
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function definitionRuleToParser(&$rule, $arguments)
    {
        if ($rule instanceof Definition) {
            return new Implementation($rule, $arguments);
        }

        return null;
    }

    /**
     * @param mixed $rule
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function parserRuleToParser(&$rule)
    {
        if ($rule instanceof Parser) {
            return clone $rule;
        }

        return null;
    }

    /**
     * @param mixed $rule
     * @param array $arguments
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function callableRuleToParser(&$rule, $arguments)
    {
        if (is_callable($rule)) {
            return $rule(...$arguments);
        }

        return null;
    }

    /**
     * @param mixed $rule
     * @param array $arguments
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function classnameRuleToParser(&$rule, $arguments)
    {
        if (is_string($rule)
            && class_exists($rule)
            && is_subclass_of($rule, Parser::class)) {
            return new $rule(...$arguments);
        }

        return null;
    }

    /**
     * @param mixed $rule
     * @return Parser
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function argumentRuleToParser(&$rule)
    {
        if (is_array($rule)
            || is_string($rule)
            || is_int($rule)) {
            return self::getArgument($rule);
        }

        return null;
    }

    /**
     * @param Mixed $rule
     * @param $arguments
     * @return Parser|null
     */
    private static function ruleToParser(&$rule, $arguments)
    {
        foreach (self::$ruleToParserMethods as $ruleToParserMethod) {
            $instance = self::$ruleToParserMethod($rule, $arguments);
            if ($instance) {
                return $instance;
            }
        }

        return null;
    }
}