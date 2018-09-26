<?php

namespace Vanderlee\Comprehend\Core;

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
     * Convert the argument to a parser
     *
     * @param mixed $argument
     * @param bool $arrayToSequence if argument is an array, convert to Sequence (`true`) or Choice (`false`)
     * @return Parser
     */
    protected static function getArgument($argument, $arrayToSequence = true)
    {
        if (is_array($argument)) {
            if (empty($argument)) {
                throw new \InvalidArgumentException('Empty array argument');
            } elseif (count($argument) === 1) {
                return self::getArgument(reset($argument));
            }

            return $arrayToSequence ? new Sequence(...$argument) : new Choice(...$argument);
        } elseif (is_string($argument)) {
            switch (strlen($argument)) {
                case 0:
                    throw new \InvalidArgumentException('Empty argument');
                case 1:
                    return new Char($argument);
                default:
                    return new Text($argument);
            }
        } elseif (is_int($argument)) {
            return new Char($argument);
        } elseif ($argument instanceof Parser) {
            return $argument;
        }

        throw new \InvalidArgumentException(sprintf('Invalid argument type `%1$s`',
            is_object($argument) ? get_class($argument) : gettype($argument)));
    }

    protected static function getArguments($arguments, $arrayToSequence = true)
    {
        return array_map(function ($argument) use ($arrayToSequence) {
            return self::getArgument($argument, $arrayToSequence);
        }, $arguments);
    }
}
