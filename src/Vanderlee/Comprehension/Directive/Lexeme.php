<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\directive;

/**
 * Description of LexemeDirective
 *
 * @author Martijn
 */
class Lexeme extends AbstractDirective {

	private $parser = null;

	public function __construct($parser)
	{
		$this->parser = ParserUtil::getParserArg($parser);
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		$context->pushSkipper();
		$match = $this->parser->doParse($in, $offset, $context);
		$context->popSkipper();
		return $match;
	}

}
