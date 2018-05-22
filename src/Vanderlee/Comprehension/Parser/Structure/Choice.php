<?php

namespace vanderlee\comprehension\parser\structure;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Description of OrParser
 *
 * @author Martijn
 */
class Choice extends AbstractParser {

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
		switch ($context->getOrMode()) {
			default:
			case Context::OR_FIRST:
				$max = 0;
				foreach ($this->parsers as $parser) {
					$match = $parser->parse($in, $offset, $context);
					if ($match->match) {
						return $this->createMatch($in, $offset, $match->length, $match);
					}
					$max = max($max, $match->length);
				}
				return $this->createMismatch($in, $offset, $max);
				break;

			case Context::OR_LONGEST:
				$max_match = $this->createMismatch($in, $offset);
				foreach ($this->parsers as $parser) {
					$match = $parser->parse($in, $offset, $context);
					if ($match->match == $max_match->match) {
						if ($match->length > $max_match->length) {
							$max_match = $match->match ? $this->createMatch($in, $offset, $match->length, $match) :
									$this->createMismatch($in, $offset, $match->length);
						}
					} elseif ($match->match) {
						$max_match = $this->createMatch($in, $offset, $match->length, $match);
					}
				}
				return $max_match;

				/*
				  $isMatch	= FALSE;
				  $length		= 0;
				  foreach ($this->parsers as $parser) {
				  $match = $parser->doParse($in, $offset, $context);
				  if ($match->match == $isMatch) {
				  $length		= max($length, $match->length);
				  } else if ($match->match) {
				  $isMatch	= TRUE;
				  $length		= $match->length;
				  }
				  }
				  return new ParserMatch($isMatch, $length); */
				break;

			case Context::OR_SHORTEST:
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
				return $match->match ? $this->createMatch($in, $offset, $match->length, $match) :
						$this->createMismatch($in, $offset, $match->length);
				break;
		}
	}

	public function __toString()
	{
		return '( ' . join(' | ', $this->parsers) . ' )';
	}

}
