<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of RangeParser
 *
 * @author Martijn
 */
class Range extends Parser {
	
	use CaseSensitiveTrait;

	private $first = null;
	private $last = null;
	private $in = true;

	public function __construct($first, $last, $in = true)
	{
		if ( $first === null && $last === null ) {
			throw new \Exception('Empty arguments');
		}
		
		$this->first = $first === null ? null : self::parseCharacter($first);
		$this->last = $last === null ? null : self::parseCharacter($last);
		$this->in = (bool) $in;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		if ($this->first === null && $this->last === null) {
			return $this->failure($in, $offset, self::INVALID_ARGUMENTS);
		}

		if ($offset >= mb_strlen($in)) {
			return $this->failure($in, $offset);			
		}
		
		$this->pushCaseSensitivityToContext($context);
		
		$first = ord($context->handleCase($this->first));
		$last = ord($context->handleCase($this->last));
		$ord = ord($context->handleCase($in[$offset]));
		if ($first <= $ord && ($this->last === null || $ord <= $last)) {
			$this->popCaseSensitivityFromContext($context);
			
			return $this->in ? $this->success($in, $offset, 1) : $this->failure($in, $offset);
		}
		
		$this->popCaseSensitivityFromContext($context);
		
		return $this->in ? $this->failure($in, $offset) : $this->success($in, $offset, 1);
	}
	
	public function __toString()
	{
		return sprintf('x%02x-x%02x', ord($this->first), ord($this->last));
	}

}
