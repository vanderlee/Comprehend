<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\directive;

/**
 * Description of OrDirective
 *
 * @author Martijn
 */
class Choice extends AbstractDirective {

	private $parser = null;
	private $or_mode = null;

	public function __construct($parser, $or_mode)
	{
		$this->parser = ParserUtil::getParserArg($parser);
		$this->or_mode = $or_mode;
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		$context->pushOrMode($this->or_mode);
		$match = $this->parser->doParse($in, $offset, $context);
		$context->popOrMode();
		return $match;
	}

}
