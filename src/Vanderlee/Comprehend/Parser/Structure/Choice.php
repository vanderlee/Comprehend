<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\core\ArgumentsTrait;
use \vanderlee\comprehend\parser\structure\PreferTrait;
use \vanderlee\comprehend\directive\Prefer;

/**
 * Description of OrParser
 *
 * @author Martijn
 */
class Choice extends Parser {

	use ArgumentsTrait;
	use PreferTrait;

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
		$this->pushPrefererenceToContext($context);
		
		switch ($context->getPreference()) {
			default:
			case Prefer::FIRST:
				$max = 0;
				foreach ($this->parsers as $parser) {
					$match = $parser->parse($in, $offset, $context);
					if ($match->match) {
						$preferred_match = $this->success($in, $offset, $match->length, $match);
						break 2;
					}
					$max = max($max, $match->length);
				}
				$preferred_match = $this->failure($in, $offset, $max);
				break;

			case Prefer::LONGEST:
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
				$preferred_match = $max_match;
				break;

			case Prefer::SHORTEST:
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
				$preferred_match =$match->match ? $this->success($in, $offset, $match->length, $match) :
						$this->failure($in, $offset, $match->length);
				break;
		}
		
		$this->popPreferenceFromContext($context);
		
		return $preferred_match;
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
		$prefix = $this->preference === Prefer::LONGEST ? 'longest-of' :
				($this->preference === Prefer::SHORTEST ? 'shortest-of' :
				'first-of');

		return $prefix . '( ' . join(' | ', $this->parsers) . ' )';
	}

}
