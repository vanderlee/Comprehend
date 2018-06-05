<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\ArgumentsTrait;

/**
 * Description of RepeatParser
 *
 * @author Martijn
 */
class Repeat extends Parser {

	use ScanningTrait;	
	use ArgumentsTrait;
	
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
		$this->pushScannerToContext($context);

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

		$this->popScannerFromContext($context);
		
		return $match ? $this->success($in, $offset, $total, $child_matches) : $this->failure($in, $offset, $total);
	}
	
	public function __toString()
	{		
		// Output ABNF formatting
		
		$min = $this->min > 0 ? $this->min : '';
		$max = $this->max === null ? '' : $this->max;
		
		return $min . '*' . $max . $this->parser;
	}

}
