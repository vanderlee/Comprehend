<?php

namespace Vanderlee\Comprehend\Core;

use InvalidArgumentException;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * Process arguments
 *
 * @author Martijn
 */
trait ArgumentsTrait
{

    /**
     * Parser array argument
     *
     * @param mixed $argument
     * @param bool  $arrayToSequence
     *
     * @return Parser|Choice|Sequence
     */
    private static function getArrayArgument($argument, $arrayToSequence)
    {
        if (empty($argument)) {
            throw new InvalidArgumentException('Empty array argument');
        }

        if (count($argument) === 1) {
            return self::getArgument(reset($argument));
        }

        return $arrayToSequence
            ? new Sequence(...$argument)
            : new Choice(...$argument);
    }

    /**
     * Parse string argument
     *
     * @param mixed $argument
     *
     * @return Char|Text
     */
    private static function getStringArgument($argument)
    {
        if (strlen($argument) === 0) {
            throw new InvalidArgumentException('Empty argument');
        }

        if (strlen($argument) === 1) {
            return new Char($argument);
        }

        return new Text($argument);
    }

    /**
     * Convert the argument to a parser
     *
     * @param mixed $argument
     * @param bool  $arrayToSequence if argument is an array, convert to Sequence (`true`) or Choice (`false`)
     *
     * @return Parser
     *
     * @throws InvalidArgumentException
     */
    protected static function getArgument($argument, $arrayToSequence = true)
    {
        if (is_array($argument)) {
            return self::getArrayArgument($argument, $arrayToSequence);
        }

        if (is_string($argument)) {
            return self::getStringArgument($argument);
        }

        if (is_int($argument)) {
            return new Char($argument);
        }

        if ($argument instanceof Parser) {
            return $argument;
        }

        throw new InvalidArgumentException(sprintf('Invalid argument type `%1$s`',
            self::getArgumentType($argument)
        ));
    }

    /**
     * Parse an array of arguments
     *
     * @param array $arguments
     * @param bool  $arrayToSequence
     *
     * @return array
     */
    protected static function getArguments($arguments, $arrayToSequence = true)
    {
        return array_map(function ($argument) use ($arrayToSequence) {
            return self::getArgument($argument, $arrayToSequence);
        }, $arguments);
    }

    /**
     * Convert a variable type to a string
     *
     * @param mixed $variable
     *
     * @return string
     */
    protected static function getArgumentType($variable)
    {
        return is_object($variable)
            ? get_class($variable)
            : gettype($variable);
    }
}
