<?php

namespace vanderlee\comprehend\directive;

use \vanderlee\comprehend\parser\Parser;

/**
 * Description of CaseDirective
 *
 * @author Martijn
 */
class CaseSensitive extends Parser {

	/**
	 * @var Parser
	 */
	private $parser = null;
	
	/**
	 * @var bool
	 */
	private $case_sensitive = null;

	/**
	 * 
	 * @param Parser|string|integer $parser
	 * @param bool $case_sensitive
	 */
	public function __construct($parser, bool $case_sensitive = true)
	{
		$this->parser = ParserUtil::getParserArg($parser);
		$this->case_sensitive = (bool) $case_sensitive;
	}

	protected function parse($in, $offset, ParserContext $context)
	{
		$context->pushCaseSensitivity($this->case_sensitive);
		$match = $this->parser->parse($in, $offset, $context);
		$context->popCaseSensitivity();
		
		return $match;
	}

}
