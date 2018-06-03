<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Matches any single symbol
 *
 * @author Martijn
 */
class Any extends Parser {

	protected function parse(string &$in, int $offset, Context $context)
	{
		if ($offset < mb_strlen($in)) {
			return $this->success($in, $offset, 1);
		}

		return $this->failure($in, $offset);
	}

	public function __toString()
	{
		return '.';
	}

}
