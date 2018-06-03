<?php

namespace vanderlee\comprehend\directive;

use \vanderlee\comprehend\directive\AbstractDirective;
use \vanderlee\comprehend\core\Context;

/**
 * Description of OrDirective
 *
 * @author Martijn
 */
class Choice extends AbstractDirective {

	private $parser = null;
	private $or_mode = null;

	/**
	 * 
	 * @param \vanderlee\comprehend\parser\structure\Choice $parser
	 * @param int $or_mode
	 */
	public function __construct(\vanderlee\comprehend\parser\structure\Choice $parser, int $or_mode = 0)
	{
		$this->parser = $parser;
		$this->or_mode = $or_mode;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$context->pushOrMode($this->or_mode);
		$match = $this->parser->parse($in, $offset, $context);
		$context->popOrMode();
		return $match;
	}
	
	public function __toString()
	{
		switch($this->or_mode) {
			default:
			case Context::OR_FIRST:
				return 'first-of' . $this->parser;
			case Context::OR_LONGEST:
				return 'longest-of' . $this->parser;
			case Context::OR_SHORTEST:
				return 'shortest-of' . $this->parser;
		}
	}

}
