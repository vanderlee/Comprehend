<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\parser\structure;

/**
 * Description of NotParser
 *
 * @author Martijn
 */
class Not extends Parser {

	private $parser = null;

	public function __construct($parser)
	{
		$this->parser = ParserUtil::getParserArg($parser);
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		if (!$this->parser instanceof Parser)
			return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);

		$match = $this->parser->doParse($in, $offset, $context);
		return new ParserMatch(!$match->match, $match->length);
	}

}
