<?php

namespace vanderlee\comprehend\parser;

use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\match\Success;
use \vanderlee\comprehend\match\Failure;
use \vanderlee\comprehend\traits\Assign;
use \vanderlee\comprehend\parser\terminal\Char;
use \vanderlee\comprehend\parser\terminal\Text;

abstract class Parser {
	
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

	/**
	 * @return \vanderlee\comprehend\match\Match;
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
	
	public function __invoke(string $in, int $offset = 0)
	{
		return $this->match($in, $offset);
	}

	/**
	 * Create a succesful match
	 * 
	 * @param string $in
	 * @param int $offset
	 * @param int $length
	 * @param Success[]|Success $successes
	 * @return Success
	 */
	protected function success(string &$in, int $offset, int $length = 0, &$successes = [])
	{
		$callbacks = $this->callbacks;
		$names = $this->names;

		$successes = is_array($successes) ? $successes : [$successes];

		return (new Success($length, $successes))
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
	 * Create a failed match
	 * 
	 * @param string $in
	 * @param int $offset
	 * @param int $length
	 * @return Failure
	 */
	protected function failure(string &$in, int $offset, int $length = 0)
	{
		return new Failure($length);
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

}
