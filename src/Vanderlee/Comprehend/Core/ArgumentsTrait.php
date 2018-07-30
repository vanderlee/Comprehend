<?php

namespace vanderlee\comprehend\core;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\parser\terminal\Char;
use \vanderlee\comprehend\parser\terminal\Text;
use \vanderlee\comprehend\parser\structure\Sequence;

/**
 * Process arguments
 * 
 * @author Martijn
 */
trait ArgumentsTrait {

	protected static function getArgument($argument)
	{
		if (is_array($argument)) {
			if (empty($argument)) {
				throw new \Exception('Empty array argument');
			} elseif (count($argument) === 1) {
				return self::getArgument(reset($argument));
			}
			
			return new Sequence(...$argument);
		} elseif (is_string($argument)) {
			switch (strlen($argument)) {
				case 0: throw new \Exception('Empty argument');
				case 1: return new Char($argument);
				default: return new Text($argument);
			}
		} elseif (is_int($argument)) {
			return new Char($argument);
		} elseif ($argument instanceof Parser) {
			return $argument;
		}

		throw new \Exception(sprintf('Invalid argument type `%1$s`', is_object($argument) ? get_class($argument) : gettype($argument)));
	}

	protected static function getArguments($arguments)
	{	
		return array_map([__CLASS__, 'getArgument'], $arguments);
	}

	private static function array_flatten($a, $f = [])
	{
		if (!$a || !is_array($a)) {
			return [];
		}
		foreach ($a as $k => $v) {
			if (is_array($v)) {
				$f = self::array_flatten($v, $f);
			} else {
				$f[$k] = $v;
			}
		}

		return $f;
	}

}
