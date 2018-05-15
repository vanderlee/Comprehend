<?php

namespace vanderlee\comprehension\parser\terminal;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Matches regular expressions
 *
 * @author Martijn
 */
class Regex extends AbstractParser {

	private $pattern = null;

	public function __construct($pattern)
	{
		if (empty($pattern)) {
			throw new \Exception('Empty pattern');
		}
		
		if (@preg_match($pattern, null) === false) {
			throw new \Exception('Invalid pattern');			
		}
		
		$this->pattern = $pattern;
	}

	protected function doParse(string &$in, int $offset, Context $context)
	{
		$pattern = $this->pattern . ($context->isCaseSensitive() ? '' : 'i');

		if (preg_match($pattern, $in, $m, 0, $offset) !== FALSE) {
			if (count($m) > 0 && mb_strlen($m[0]) > 0 && strpos($in, $m[0], $offset) == $offset) {
				return $this->createMatch($in, $offset, mb_strlen($m[0]));
			}
		}
		
		return $this->createMismatch($in, $offset);
	}
	
	public function __toString()
	{
		return $this->pattern;
	}

}
