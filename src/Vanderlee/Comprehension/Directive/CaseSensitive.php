<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\directive;

/**
 * Description of CaseDirective
 *
 * @author Martijn
 */
class CaseSensitive extends AbstractDirective {

	private $parser = null;
	private $case_sensitive = null;

	public function __construct($parser, $case_sensitive)
	{
		$this->parser = ParserUtil::getParserArg($parser);
		$this->case_sensitive = (bool) $case_sensitive;
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		$context->pushCaseSensitive($this->case_sensitive);
		$match = $this->parser->doParse($in, $offset, $context);
		$context->popCaseSensitive();
		return $match;
	}

}
