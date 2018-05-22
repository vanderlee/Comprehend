<?php

namespace vanderlee\comprehension\parser;

use \vanderlee\comprehension\core\Context;
use \vanderlee\comprehension\core\Match;
use \vanderlee\comprehension\traits\Assign;
use \vanderlee\comprehension\parser\terminal\Char;
use \vanderlee\comprehension\parser\terminal\Text;

abstract class AbstractParser {

	const INVALID_ARGUMENTS = PHP_INT_MIN;

	/**
	 * List of result names to assign the matched text to.
	 * @var string
	 */
	private $names = [];

	/**
	 * List of callbacks to call when this parser has matched a part of the
	 * full parse.
	 * @var type 
	 */
	private $callbacks = [];

	/**
	 * @return \vanderlee\comprehension\core\Match;
	 */
	abstract protected function parse(string &$in, int $offset, Context $context);

	/**
	 * @param string $in
	 * @param integer $offset
	 * @return Match;
	 */
	public function match(string $in, int $offset = 0)
	{
		if ($offset < 0) {
			throw new \Exception("Negative offset");
		}

		$match = $this->parse($in, $offset, new Context());

		$match->resolve();

		return $match;
	}

	/**
	 * Create a mismatch
	 * 
	 * @param string $in
	 * @param int $offset
	 * @param int $length
	 * @param Match[] $child_matches
	 * @return Match
	 */
	protected function createMismatch(string &$in, int $offset, int $length = 0, &$child_matches = [])
	{
		return new Match(false, $length);
	}

	/**
	 * Create a match
	 * 
	 * @param string $in
	 * @param int $offset
	 * @param int $length
	 * @param Match[] $child_matches
	 * @return Match
	 */
	protected function createMatch(string &$in, int $offset, int $length, &$child_matches = [])
	{
		$callbacks = $this->callbacks;
		$names = $this->names;

		$child_matches = is_array($child_matches) ? $child_matches : [$child_matches];

		return (new Match(true, $length, $child_matches))
						->callback(function(&$results) use($in, $offset, $length, $callbacks, $names) {
							$matchedText = substr($in, $offset, $length);

							foreach ($names as $name) {
								$results[$name] = $matchedText;
							}

							foreach ($callbacks as $callback) {
								$callback($matchedText, $in, $offset, $length);
							}
						});
	}

	/**
	 * After parsing, assign the matched input of this parser to the named
	 * result. Only assign if successfully matched entire parent upto root.
	 * 
	 * @param string|integer $key
	 * @return $this
	 */
	public function resultAs($key)
	{
		$this->names[] = $key;
		return $this;
	}

	public function callback(callable $callback)
	{
		$this->callbacks[] = $callback;
		return $this;
	}

	/**
	 * After parsing, assign the matched input to the specified local variable.
	 * Only assign if successfully matched entire parent upto root.
	 *  
	 * @param type $variable
	 * @return $this
	 */
	public function assignTo(&$variable)
	{
		$this->callbacks[] = function($matchedText) use (&$variable) {
			$variable = $matchedText;
		};
		return $this;
	}

	protected static function parseCharacter($character)
	{
		if (empty($character)) {
			throw new \Exception('Empty argument');
		}

		if (is_int($character)) {
			return chr($character);
		} elseif (mb_strlen($character) > 1) {
			throw new \Exception('Non-character argument');
		}

		return $character;
	}	

	protected static function getArgument($argument)
	{
		// Get very first non-array value of (recursive) array
		while (is_array($argument)) {
			$argument = reset($argument);
		}

		if (is_string($argument)) {
			switch (strlen($argument)) {
				case 0: return false;
				case 1: return new Char($argument);
				default: return new Text($argument);
			}
		} elseif (is_int($argument)) {
			return new Char($argument);
		} elseif ($argument instanceof AbstractParser) {
			return $argument;
		}

		throw new \Exception(sprintf('Invalid argument type `%1$s`.', gettype($argument)));
	}

	protected static function getArguments(...$arguments)
	{
		return array_map('self::getArgument', self::array_flatten($arguments));
	}

	private static function array_flatten($a, $f = [])
	{
		if (!$a || !is_array($a)) {
			return [];
		}
		foreach ($a as $k => $v) {
			if (is_array($v)) {
				$f = self::array_flatten($v, $f);
			} else {
				$f[$k] = $v;
			}
		}

		return $f;
	}

}
