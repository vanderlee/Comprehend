<?php

namespace vanderlee\comprehension\parser\terminal;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Matches any single symbol
 *
 * @author Martijn
 */
class Any extends AbstractParser {

	protected function parse(string &$in, int $offset, Context $context)
	{
		if ($offset < mb_strlen($in)) {
			return $this->createMatch($in, $offset, 1);
		}

		return $this->createMismatch($in, $offset);
	}

	public function __toString()
	{
		return '.';
	}

}
