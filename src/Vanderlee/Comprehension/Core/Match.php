<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace vanderlee\comprehension\core;

/**
 * Description of ParserToken
 *
 * @author Martijn
 */
class Match {

	private $match;
	private $length;
	private $results = [];
	
	private $child_matches = [];
	private $callbacks = [];
	
	public function __get($name) {
		switch($name) {
			case 'match':	return $this->match;
			case 'length':	return $this->length;
			case 'results':	return $this->results;
		}
		
		throw new \Exception("Property name `{$name}` not recognized");
	}

	/**
	 * Create a new match
	 * @param boolean $match
	 * @param int $length
	 * @param Match[]|Match $child_matches
	 */
	public function __construct(bool $match, int $length, &$child_matches = [])
	{
		$this->match = $match;
		$this->length = $length;
		$this->child_matches = $child_matches;
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
		array_walk($this->child_matches, function($child_match) use(&$results) {
			$child_match->processCallbacks($results);
		});
		
		array_walk($this->callbacks, function($callback) use(&$results) {
			$callback($results);
		});
	}

	public function resolve()
	{
		$this->processCallbacks($this->results);
	}

}
