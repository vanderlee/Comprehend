<?php

namespace vanderlee\comprehend\match;

use \vanderlee\comprehend\core\ParserException;

/**
 * Description of ParserToken
 *
 * @author Martijn
 */
class Success extends Match {

	/**
	 * Boolean state indicating whether this match has been resolved already.
	 * Each match may only be resolved once to prevent conflicts.
	 * 
	 * @var bool
	 */
	private $resolved = false;
	
	/**
	 * Map of resolved result callbacks
	 * 
	 * @var array|null
	 */
	private $results = null;

	/**
	 * @var Success[]
	 */
	private $successes = [];

	/**
	 * List of partial-resolvable result callbacks
	 * @var callable[] 
	 */
	private $resultCallbacks = [];

	/**
	 * List of ordinary callbacks to process
	 * @var callable[]
	 */
	private $customCallbacks = [];

	/**
	 * Create a new match
	 * @param int $length
	 * @param Success[]|Success $successes
	 */
	public function __construct(int $length = 0, &$successes = [])
	{
		parent::__construct($length);

		$this->successes = $successes;
	}

	public function __get($name)
	{
		switch ($name) {
			case 'match':
				return true;
			case 'results':
				$results = $this->getResults();
				unset($results[null]);
				return $results;
			case 'result':
				return $this->getResults()[null] ?: null;
		}

		return parent::__get($name);
	}

	/**
	 * Add a callback to this match, to be called after parsing is finished and
	 * only if this match was part of the matched rules.
	 * 
	 * @param callable $callback
	 * @return Match
	 */
	public function addCustomCallback(callable $callback)
	{
		$this->customCallbacks[] = $callback;

		return $this;
	}

	/**
	 * Add a callback to this match, to be called after parsing is finished and
	 * only if this match was part of the matched rules.
	 * 
	 * @param callable $callback
	 * @return Match
	 */
	public function addResultCallback(callable $callback)
	{
		$this->resultCallbacks[] = $callback;

		return $this;
	}

	/**
	 * Handle all registered result callbacks for this match and any matches
	 * at deeper levels of this match.
	 * 
	 * @param array $results map of result-key => value
	 */
	private function processResultCallbacks(&$results)
	{
		array_walk($this->successes, function($success) use(&$results) {
			$success->processResultCallbacks($results);
		});

		array_walk($this->resultCallbacks, function($callback) use(&$results) {
			$callback($results);
		});
	}

	/**
	 * Handle all registered custom callbacks for this match and any matches
	 * at deeper levels of this match.
	 */
	private function processCustomCallbacks()
	{
		if ($this->resolved) {
			throw new ParserException('Match already resolved');
		}
		$this->resolved = true;
		
		array_walk($this->successes, function($success) {
			$success->processCustomCallbacks();
		});

		array_walk($this->customCallbacks, function($callback) {
			$callback();
		});
	}

	/**
	 * Resolve any custom callbacks
	 * 
	 * Chainable
	 */
	public function resolve()
	{		
		$this->processCustomCallbacks();		

		return $this;
	}

	/**
	 * Precalculate results
	 * @return array
	 */
	public function getResults()
	{
		if ($this->results === null) {
			$this->results = [];
			$this->processResultCallbacks($this->results);
		}

		return $this->results;
	}

	public function getResult($name = null, $default = null)
	{
		return $this->getResults()[$name] ?? $default;
	}

	public function hasResult($name = null)
	{
		return isset($this->getResults()[$name]);
	}

	public function __toString()
	{
		return 'Successfully matched ' . $this->length . ' characters';
	}

}
