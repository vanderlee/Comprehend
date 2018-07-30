<?php

namespace vanderlee\comprehend\parser;

/**
 *
 * @author Martijn
 */
trait ResultTrait {

	/**
	 * List of result names to assign the matched text to.
	 * @var string
	 */
	private $resultCallbacks = [];

	private function resolveResultCallbacks(&$results, $text)
	{
		foreach ($this->resultCallbacks as $callback) {
			$callback($results, $text);
		}
	}

	/**
	 * After parsing, assign the matched input of this parser to the named
	 * result. Only assign if successfully matched entire parent upto root.
	 * 
	 * @param string|integer $key
	 * @return $this
	 */
	public function setResult($key = null, $value = null)
	{
		$this->resultCallbacks[] = function(&$results, $text) use (&$key, &$value) {
			$results[$key] = $value ?: $text;
		};

		return $this;
	}

	/**
	 * If result exists, concatenate the matched text as a string, otherwise
	 * create it. If result is an array, concat to the last entry.
	 * 
	 * @param type $key
	 */
	public function concatResult($key = null, $value = null)
	{
		$this->resultCallbacks[] = function(&$results, $text) use (&$key, &$value) {
			$text = $value ?: $text;
			
			if (!isset($results[$key])) {
				$results[$key] = (string) $text;
			} elseif (is_array($results[$key])) {
				$results[$key][] = array_pop($results[$key]) . $text;
			} else {
				$results[$key] .= $text;
			}
		};

		return $this;
	}

	/**
	 * Turn the result into an array and start a new entry.
	 * 
	 * @param type $key
	 */
	public function pushResult($key = null, $value = null)
	{
		$this->resultCallbacks[] = function(&$results, $text) use (&$key, &$value) {
			$text = $value ?: $text;
			
			if (!isset($results[$key])) {
				$results[$key] = [$text];
			} elseif (is_array($results[$key])) {
				$results[$key][] = $text;
			} else {
				$results[$key] = [$results[$key], $text];
			}
		};

		return $this;
	}

}
