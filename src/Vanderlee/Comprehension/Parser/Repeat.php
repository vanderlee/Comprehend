<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\parser;

/**
 * Description of RepeatParser
 *
 * @author Martijn
 */
class Repeat extends Parser {

	private $parser = null;
	private $min = null;
	private $max = null;

	public function __construct($parser, $min = 0, $max = null)
	{
		$this->parser = ParserUtil::getParserArg($parser);
		$this->min = $min;
		$this->max = $max;
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		if ($this->max !== null && $this->max < $this->min)
			return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);

		$total = 0;
		$matches = 0;
		$tokens = array();
		do {
			$skip = $context->skip($in, $offset + $total);
			$match = $this->parser->doParse($in, $offset + $total + $skip, $context);
			if ($match->match) {
				if (!$this->hasToken() && count($match->tokens)) {
					$tokens = array_merge($tokens, $match->tokens);
				}
				$total += $skip + $match->length;
				++$matches;
			}
		} while ($match->match && ($this->max == null || $matches < $this->max));

		return new ParserMatch($matches >= $this->min && ($this->max == null || $matches <= $this->max)
				, $total
				, $this->hasToken() ? $this->makeToken($offset, $total) : $tokens);
	}

}
