<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\core\ArgumentsTrait;

/**
 * Description of OrParser
 *
 * @author Martijn
 */
class Choice extends Parser {
	
	use ArgumentsTrait;

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
		switch ($context->getPreference()) {
			default:
			case Context::PREFER_FIRST:
				$max = 0;
				foreach ($this->parsers as $parser) {
					$match = $parser->parse($in, $offset, $context);
					if ($match->match) {
						return $this->success($in, $offset, $match->length, $match);
					}
					$max = max($max, $match->length);
				}
				return $this->failure($in, $offset, $max);
				break;

			case Context::PREFER_LONGEST:
				$max_match = $this->failure($in, $offset);
				foreach ($this->parsers as $parser) {
					$match = $parser->parse($in, $offset, $context);
					if ($match->match == $max_match->match) {
						if ($match->length > $max_match->length) {
							$max_match = $match->match ? $this->success($in, $offset, $match->length, $match) :
									$this->failure($in, $offset, $match->length);
						}
					} elseif ($match->match) {
						$max_match = $this->success($in, $offset, $match->length, $match);
					}
				}
				return $max_match;
				break;

			case Context::PREFER_SHORTEST:
				$match = null;
				foreach ($this->parsers as $parser) {
					$attempt = $parser->parse($in, $offset, $context);

					switch (true) {
						case!$match: // Keep attempt if first.
						case $attempt->match && !$match->match: // Keep attempt if first match
						case $attempt->match === $match->match && $attempt->length < $match->length: // Keep attempt if equally succesful but shorter
							$match = $attempt;
					}
				}

				// This will fail! $match is not necesarily the shortest
				return $match->match ? $this->success($in, $offset, $match->length, $match) :
						$this->failure($in, $offset, $match->length);
				break;
		}
	}
	
	/**
	 * Add one or more parsers as choices
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
		return '( ' . join(' | ', $this->parsers) . ' )';
	}

}