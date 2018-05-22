<?php

namespace vanderlee\comprehension\parser\structure;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Description of AndParser
 *
 * @author Martijn
 */
class All extends AbstractParser {

	private $parsers = null;

	public function __construct(...$arguments)
	{
		if (count($arguments) < 2) {
			throw new \Exception('Less than 2 arguments provided');
		}
		$this->parsers = self::getArguments($arguments);
	}

	protected function parse($in, $offset, ParserContext $context)
	{
		$length = PHP_INT_MAX;
		foreach ($this->parsers as $parser) {
			$match = $parser->parse($in, $offset, $context);
			if (!$match->match) {
				return $this->createMismatch($in, $offset);
			} else {
				$length = min($length, $match->length);
			}
		}
		return $this->createMatch($in, $offset, $length);
	}

}
