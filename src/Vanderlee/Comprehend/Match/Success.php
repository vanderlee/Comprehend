<?php

namespace vanderlee\comprehend\match;

/**
 * Description of ParserToken
 *
 * @author Martijn
 */
class Success extends Match {

	private $results = [];
	
	/**
	 *
	 * @var Success[]
	 */
	private $successes = [];
	private $callbacks = [];
	
	public function __get($name) {
		switch($name) {
			case 'match':	return true;
			case 'results':	return $this->results;
		}
		
		return parent::__get($name);
	}

	/**
	 * Create a new match
	 * @param int $length
	 * @param Success[]|Success $successes
	 */
	public function __construct(int $length, &$successes = [])
	{
		parent::__construct($length);
		
		$this->successes = $successes;
	}

	/**
	 * Add a callback to this match, to be called after parsing is finished and
	 * only if this match was part of the matched rules.
	 * 
	 * @param callable $callback
	 * @return Match
	 */
	public function callback(callable $callback)
	{
		$this->callbacks[] = $callback;

		return $this;
	}

	private function processCallbacks(&$results)
	{
		array_walk($this->successes, function($child_match) use(&$results) {
			$child_match->processCallbacks($results);
		});
		
		$this->successes = [];
		
		array_walk($this->callbacks, function($callback) use(&$results) {
			$callback($results);
		});
		$this->callbacks = [];
	}

	/**
	 * Chainable
	 * @todo protect this?
	 */
	public function resolve()
	{
		$this->processCallbacks($this->results);
		return $this;
	}
	
	public function getResult($name, $default = null) {
		return $this->results[$name] ?? $default;
	}
	
	public function hasResult($name) {
		return isset($this->results[$name]);
	}
	
	public function __toString()
	{
		return 'Successfully matched ' . $this->length . ' characters.';
	}

}
