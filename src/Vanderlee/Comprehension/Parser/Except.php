<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\parser;

/**
 * Description of ExceptParser
 *
 * @author Martijn
 */
class Except extends Parser {

	private $parser_match = null;
	private $parser_not = null;

	public function __construct($parser_match, $parser_not)
	{
		$this->parser_match = ParserUtil::getParserArg($parser_match);
		$this->parser_not = ParserUtil::getParserArg($parser_not);
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		$match = $this->parser_match->doParse($in, $offset, $context);
		$not = $this->parser_not->doParse($in, $offset, $context);
		if ($match->match && !$not->match) {
			return $match;
		}
		return new ParserMatch(FALSE, min($match->length, $not->length));
	}

}
