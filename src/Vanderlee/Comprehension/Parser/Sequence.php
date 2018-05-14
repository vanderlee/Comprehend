<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\parser;

use vanderlee\comprehension\parser\AbstractParser;
use vanderlee\comprehension\core\Match;
use vanderlee\comprehension\core\Context;
use vanderlee\comprehension\core\Util;

/**
 * Description of SequenceParser
 *
 * @author Martijn
 */
class Sequence extends AbstractParser {

	private $parsers = null;

	public function __construct(...$arguments)
	{
		$this->parsers = $this->getArguments($arguments);
	}

	protected function doParse($in, $offset, Context $context)
	{
		$child_matches = [];

		if (!is_array($this->parsers) || count($this->parsers) < 1) {
			return $this->createMismatch(AbstractParser::INVALID_ARGUMENTS);
		}

		$total = 0;
		foreach ($this->parsers as $parser) {
			$total += $context->skip($in, $offset + $total);
			$match = $parser->doParse($in, $offset + $total, $context);
			$total += $match->length;

			if (!$match->match) {  // must match
				return $this->createMismatch($total);
			}

			$child_matches[] = $match;
		}

		//@todo add own callback?

		return $this->createMatch($in, $offset, $total, $child_matches);
	}

	public function add(...$arguments)
	{
		$this->parsers = array_merge($this->parsers, $this->getArguments($arguments));
	}

	public function __toString()
	{
		return join(' ', $this->parsers);
	}

}
