<?php

namespace vanderlee\comprehension\parser\terminal;

use \vanderlee\comprehension\parser\AbstractParser;

/**
 * Matches regular expressions
 *
 * @author Martijn
 */
class Regex extends AbstractParser {

	private $pattern = null;

	public function __construct($pattern)
	{
		$this->pattern = $pattern;
	}

	protected function doParse(string &$in, int $offset, Context $context)
	{
		// At the very least, a pattern should have delimiters.
		if (strlen($this->pattern) <= 2)
			return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);

		$pattern = $this->pattern . ($context->isCaseSensitive() ? '' : 'i');

		if (preg_match($pattern, $in, $m, 0, $offset) !== FALSE) {
			if (count($m) > 0 && strpos($in, $m[0], $offset) == $offset) {
				return new ParserMatch(TRUE, strlen($m[0]), $this->makeToken($offset, strlen($m[0])));
			}
		}
		return new ParserMatch(FALSE, 0);
	}

}
