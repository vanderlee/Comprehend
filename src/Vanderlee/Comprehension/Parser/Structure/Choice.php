<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\parser\structure;

/**
 * Description of OrParser
 *
 * @author Martijn
 */
class Choice extends Parser {

	private $parsers = null;

	public function __construct()
	{
		$this->parsers = ParserUtil::getParserArgs(func_get_args());
	}

	protected function doParse($in, $offset, ParserContext $context)
	{
		if (!is_array($this->parsers) || count($this->parsers) < 1)
			return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);

		switch ($context->getOrMode()) {
			default:
			case ParserContext::OR_FIRST:
				$max = 0;
				foreach ($this->parsers as $parser) {
					$match = $parser->doParse($in, $offset, $context);
					if ($match->match) {
						return $match;
					}
					$max = max($max, $match->length);
				}
				return new ParserMatch(FALSE, $max);
				break;

			case ParserContext::OR_LONGEST:
				$max_match = new ParserMatch(FALSE, 0);
				foreach ($this->parsers as $parser) {
					$match = $parser->doParse($in, $offset, $context);
					if ($match->match == $max_match->match) {
						if ($match->length > $max_match->length) {
							$max_match = $match;
						}
					} else if ($match->match) {
						$max_match = $match;
					}
				}
				return $max_match;

				/*
				  $isMatch	= FALSE;
				  $length		= 0;
				  foreach ($this->parsers as $parser) {
				  $match = $parser->doParse($in, $offset, $context);
				  if ($match->match == $isMatch) {
				  $length		= max($length, $match->length);
				  } else if ($match->match) {
				  $isMatch	= TRUE;
				  $length		= $match->length;
				  }
				  }
				  return new ParserMatch($isMatch, $length); */
				break;

			case ParserContext::OR_SHORTEST:
				$isMatch = FALSE;
				$length = 0;
				foreach ($this->parsers as $parser) {
					$match = $parser->doParse($in, $offset, $context);
					if ($match->match && !$isMatch) {
						$isMatch = TRUE;
						$length = $match->length;
					} else if ($match->match) {
						$length = min($length, $match->length);
					}
				}

				return new ParserMatch($isMatch, $length);
				break;
		}
	}

}
