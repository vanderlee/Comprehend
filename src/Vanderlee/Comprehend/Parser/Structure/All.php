<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\ArgumentsTrait;

/**
 * Description of AndParser
 *
 * @author Martijn
 */
class All extends Parser {

	use ArgumentsTrait;

	private $parsers = null;

	public function __construct(...$arguments)
	{
		if (count($arguments) < 2) {
			throw new \Exception('Less than 2 arguments provided');
		}
		$this->parsers = self::getArguments($arguments);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$length = PHP_INT_MAX;
		foreach ($this->parsers as $parser) {
			$match = $parser->parse($in, $offset, $context);
			$length = min($length, $match->length);
			if (!$match->match) {
				return $this->failure($in, $offset, $length);
			}
		}
		return $this->success($in, $offset, $length);
	}

	public function __toString()
	{
		return '( ' . join(' + ', $this->parsers) . ' )';
	}

}
