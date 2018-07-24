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

	use SpacingTrait;
	
	private $parsers = null;

	public function __construct(...$arguments)
	{
		if (empty($arguments)) {
			throw new \Exception('No arguments');
		}
		
		$this->parsers = self::getArguments($arguments);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$child_matches = [];

		$this->pushSpacer($context);

		$total = 0;
		foreach ($this->parsers as $parser) {
			if ($total > 0) {
				$total += $context->skipSpacing($in, $offset + $total);
			}
			$match = $parser->parse($in, $offset + $total, $context);
			$total += $match->length;

			if (!$match->match) {  // must match
				$this->popSpacer($context);
				
				return $this->failure($in, $offset, $total);
			}

			$child_matches[] = $match;
		}

		$this->popSpacer($context);

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
		
		return $this;		
	}

	public function __toString()
	{
		return '( ' . join(' ', $this->parsers) . ' )';
	}

}
