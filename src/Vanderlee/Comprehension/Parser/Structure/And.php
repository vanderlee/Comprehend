<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\parser\structure;

/**
 * Description of AndParser
 *
 * @author Martijn
 */
class And extends Parser {

	private $parsers = null;

	public function __construct()
	{
		$this->parsers = ParserUtil::getParserArgs(func_get_args());
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		// Atleast two terms
		if (count($this->parsers) < 2)
			return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);

		$length = PHP_INT_MAX;
		foreach ($this->parsers as $parser) {
			$match = $parser->doParse($in, $offset, $context);
			if (!$match->match) {
				return new ParserMatch(FALSE, 0);
			} else {
				$length = min($length, $match->length);
			}
		}
		return new ParserMatch(TRUE, $length);
	}

}
