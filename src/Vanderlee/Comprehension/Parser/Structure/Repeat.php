<?php

namespace vanderlee\comprehension\parser\structure;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Description of RepeatParser
 *
 * @author Martijn
 */
class Repeat extends AbstractParser {

	private $parser = null;
	private $min = null;
	private $max = null;

	public function __construct($parser, $min = 0, $max = null)
	{
		$this->parser = $this->getArgument($parser);
		$this->min = $min;
		$this->max = $max;

		if ($this->max !== null && $this->max < $this->min) {
			throw new \Exception('Invalid repeat range specified');
		}
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$child_matches = [];

		$total = 0;
		$matches = 0;
		$tokens = array();
		do {
			$skip = $context->skip($in, $offset + $total);
			$match = $this->parser->parse($in, $offset + $total + $skip, $context);
			if ($match->match) {
				$total += $skip + $match->length;
				$child_matches[] = $match;
			}
		} while ($match->match && ($this->max == null || count($child_matches) < $this->max));

		$match = (count($child_matches) >= $this->min) && ($this->max == null || count($child_matches) <= $this->max);

		return $match ? $this->createMatch($in, $offset, $total, $child_matches) : $this->createMismatch($in, $offset, $total);
	}
	
	public function __toString()
	{		
		// Output ABNF formatting
		
		$min = $this->min > 0 ? $this->min : '';
		$max = $this->max === null ? '' : $this->max;
		
		return $min . '*' . $max . $this->parser;
	}

}
