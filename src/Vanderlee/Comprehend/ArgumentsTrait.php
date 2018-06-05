<?php

namespace vanderlee\comprehend;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\parser\terminal\Char;
use \vanderlee\comprehend\parser\terminal\Text;

/**
 * Process arguments
 * 
 * @author Martijn
 */
trait ArgumentsTrait {

	protected static function getArgument($argument)
	{
		// Get very first non-array value of (recursive) array
		while (is_array($argument)) {
			$argument = reset($argument);
		}
		
		if (is_string($argument)) {
			switch (strlen($argument)) {
				case 0: return false;
				case 1: return new Char($argument);
				default: return new Text($argument);
			}
		} elseif (is_int($argument)) {
			return new Char($argument);
		} elseif ($argument instanceof Parser) {
			return $argument;
		}
		
		throw new \Exception(sprintf('Invalid argument type `%1$s`.', gettype($argument)));
	}

	protected static function getArguments(...$arguments)
	{
		return array_map('self::getArgument', self::array_flatten($arguments));
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
