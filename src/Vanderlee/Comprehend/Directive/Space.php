<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehend\directive;

use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\parser\Parser;

/**
 * Description of LexemeDirective
 *
 * @author Martijn
 */
class Space extends Parser {
	
	// where's the spacer?

	/**
	 * @var \vanderlee\comprehend\parser\Parser;
	 */
	private $parser = null;

	public function __construct($parser)
	{
		$this->parser = ParserUtil::getParserArg($parser);
	}
	
	protected function parse(string &$in, int $offset, Context $context)
	{
		$context->pushSpacer();
		$match = $this->parser->parse($in, $offset, $context);
		$context->popSpacer();
		
		return $this->success($in, $offset, $match->match ? $match->length : 0);
	}

}
