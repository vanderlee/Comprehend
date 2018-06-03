<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Matches (with length 0) if the offset is at the very start of the input
 *
 * @author Martijn
 */
class Start extends Parser {

	protected function parse(string &$in, int $offset, Context $context)
	{
		return $offset == 0 ? $this->success($in, $offset) : $this->failure($in, $offset);
	}

	public function __toString()
	{
		return 'start';
	}

}
