<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Matches (with length 0) if the offset is at the very end of the input
 *
 * @author Martijn
 */
class End extends Parser {

	protected function parse(string &$in, int $offset, Context $context)
	{
		return $offset == mb_strlen($in) ? $this->success($in, $offset) : $this->failure($in, $offset);
	}

	public function __toString()
	{
		return 'end';
	}

}
