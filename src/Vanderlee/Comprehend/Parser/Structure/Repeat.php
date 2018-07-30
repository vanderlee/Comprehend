<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\core\ArgumentsTrait;

/**
 * Description of RepeatParser
 *
 * @author Martijn
 */
class Repeat extends Parser {

	const GREEDY = 0;
	const UNGREEDY = 1;

	use SpacingTrait;
	
	//use GreedyTrait;

	private $parser = null;
	private $min = null;
	private $max = null;
	private $flags = 0;

	public function __construct($parser, $min = 0, $max = null, $flags = self::GREEDY)
	{
		$this->parser = $this->getArgument($parser);
		$this->min = $min;
		$this->max = $max;
		$this->flags = $flags;

		if ($this->max !== null && $this->max < $this->min) {
			throw new \Exception('Invalid repeat range specified');
		}
	}
	
	public static function oneOrMore($parser) {
		return new self($parser, 1);
	}
	
	public static function zeroOrMore($parser) {
		return new self($parser);
	}
	
	public static function zeroOrOne($parser) {
		return new self($parser, 0, 1);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$this->pushSpacer($context);

		$child_matches = [];
		
		$length = 0;
		$tokens = array();
		do {
			$skip = $context->skipSpacing($in, $offset + $length);
			$match = $this->parser->parse($in, $offset + $length + $skip, $context);
			if ($match->match) {
				$length += $skip + $match->length;
				$child_matches[] = $match;
			}
		} while ($match->match && ($this->max == null || count($child_matches) < $this->max));

		$match = (count($child_matches) >= $this->min) && ($this->max == null || count($child_matches) <= $this->max);

		$this->popSpacer($context);

		return $match ? $this->success($in, $offset, $length, $child_matches) : $this->failure($in, $offset, $length);
	}

	public function __toString()
	{
		// Output ABNF formatting

		$min = $this->min > 0 ? $this->min : '';
		$max = $this->max === null ? '' : $this->max;

		return $min . '*' . $max . $this->parser;
	}

}
