<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\parser\terminal\Char;

/**
 * Description of SequenceParser
 *
 * @author Martijn
 */
class Sequence extends Parser {

	private $parsers = null;

	public function __construct(...$arguments)
	{
		$this->parsers = self::getArguments($arguments);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$child_matches = [];

		if (!is_array($this->parsers) || count($this->parsers) < 1) {
			return $this->failure($in, $offset, Parser::INVALID_ARGUMENTS);
		}

		$total = 0;
		foreach ($this->parsers as $parser) {
			$total += $context->skip($in, $offset + $total);
			$match = $parser->parse($in, $offset + $total, $context);
			$total += $match->length;

			if (!$match->match) {  // must match
				return $this->failure($in, $offset, $total);
			}

			$child_matches[] = $match;
		}

		//@todo add own callback?

		return $this->success($in, $offset, $total, $child_matches);
	}

	/**
	 * Add one or more parsers to the end of this sequence
	 * 
	 * @param string[]|int[]|Parser[] $arguments
	 */
	public function add(...$arguments)
	{
		$this->parsers = array_merge($this->parsers, self::getArguments($arguments));
	}

	public function __toString()
	{
		return '( ' . join(' ', $this->parsers) . ' )';
	}

}
