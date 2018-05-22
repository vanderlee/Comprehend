<?php

namespace vanderlee\comprehension\parser\structure;

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
		$match = $this->parser_match->parse($in, $offset, $context);
		$not = $this->parser_not->parse($in, $offset, $context);
		if ($match->match && !$not->match) {
			return $match;
		}
		return new ParserMatch(FALSE, min($match->length, $not->length));
	}

}
