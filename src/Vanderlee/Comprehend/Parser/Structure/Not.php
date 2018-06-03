<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of NotParser
 *
 * @author Martijn
 */
class Not extends Parser {

	private $parser = null;

	public function __construct($parser)
	{
		$this->parser = self::getArgument($parser);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$match = $this->parser->parse($in, $offset, $context);
		return $match->match ? $this->failure($in, $offset, $match->length) : $this->success($in, $offset, 0);
	}

	public function __toString()
	{
		return '!' . $this->parser;
	}

}
