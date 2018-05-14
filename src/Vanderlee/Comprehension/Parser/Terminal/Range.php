<?php

namespace vanderlee\comprehension\parser\terminal;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Description of RangeParser
 *
 * @author Martijn
 */
class Range extends AbstractParser {

	private $first = null;
	private $last = null;

	public function __construct($first, $last)
	{
		if ( $first === null && $last === null ) {
			throw new \Exception('Empty arguments');
		}
		
		$this->first = $first === null ? null : self::parseCharacter($first);
		$this->last = $last === null ? null : self::parseCharacter($last);
	}

	protected function doParse(string &$in, int $offset, Context $context)
	{
		if ($this->first === null && $this->last === null) {
			return $this->createMismatch($in, $offset, self::INVALID_ARGUMENTS);
		}

		if ($offset >= mb_strlen($in)) {
			return $this->createMismatch($in, $offset, 0);			
		}

		$first = ord($context->handleCase($this->first));
		$last = ord($context->handleCase($this->last));
		$ord = ord($context->handleCase($in[$offset]));
		if ($first <= $ord && ($this->last === null || $ord <= $last)) {
			return $this->createMatch($in, $offset, 1);			
		}
		
		return $this->createMismatch($in, $offset, 0);			
	}
	
	public function __toString()
	{
		return sprintf('x%02x-x%02x', ord($this->first), ord($this->last));
	}

}
