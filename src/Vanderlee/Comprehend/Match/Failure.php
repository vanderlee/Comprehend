<?php

namespace vanderlee\comprehend\match;

/**
 * Description of ParserToken
 *
 * @author Martijn
 */
class Failure extends Match {

	public function __get($name)
	{
		return $name === 'match' ? false : parent::__get($name);
	}

	/**
	 * Create a new match
	 * @param int $length
	 * @param Match[]|Match $child_matches
	 */
	public function __construct(int $length)
	{
		$this->length = $length;
	}

}
