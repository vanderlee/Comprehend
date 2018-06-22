<?php

namespace vanderlee\comprehend\directive;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of OrDirective
 *
 * @author Martijn
 */
class Prefer extends Parser {

	private $parser = null;
	private $preference = null;

	/**
	 * 
	 * @param \vanderlee\comprehend\parser\structure\Choice $parser
	 * @param mixed $preference
	 */
	public function __construct(\vanderlee\comprehend\parser\structure\Choice $parser, $preference = Context::PREFER_FIRST)
	{
		$this->parser = $parser;
		$this->preference = $preference;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$context->pushPreference($this->preference);
		$match = $this->parser->parse($in, $offset, $context);
		$context->popPreference();
		return $match;
	}
	
	public function __toString()
	{
		switch($this->preference) {
			default:
			case Context::PREFER_FIRST:
				return 'first-of' . $this->parser;
			case Context::PREFER_LONGEST:
				return 'longest-of' . $this->parser;
			case Context::PREFER_SHORTEST:
				return 'shortest-of' . $this->parser;
		}
	}

}
