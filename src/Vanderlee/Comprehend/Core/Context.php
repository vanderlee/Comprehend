<?php

namespace vanderlee\comprehend\core;

use \vanderlee\comprehend\core\ArgumentsTrait;

/**
 * Maintains the current context of the parser chain
 *
 * @author Martijn
 */
class Context {

	use ArgumentsTrait;

	private $skipper = [];

	public function pushSpacer($skipper = null)
	{
		array_push($this->skipper, $skipper === null ? null : $this->getArgument($skipper));
	}

	public function popSpacer()
	{
		array_pop($this->skipper);
	}

	public function skipSpacing($in, $offset)
	{
		$skipper = end($this->skipper);
		if ($skipper instanceof \vanderlee\comprehend\parser\Parser) {
			$match = $skipper->match($in, $offset);
			if ($match->match) {
				return $match->length;
			}
		}
		return 0;
	}

	private $case_sensitive = [];

	public function pushCaseSensitivity($case_sensitive = TRUE)
	{
		array_push($this->case_sensitive, (bool) $case_sensitive);
	}

	public function popCaseSensitivity()
	{
		return array_pop($this->case_sensitive);
	}

	public function isCaseSensitive()
	{
		return end($this->case_sensitive);
	}

	// Helper
	public function handleCase($text)
	{
		return $this->isCaseSensitive() ? $text : mb_strtolower($text);
	}

	const PREFER_FIRST = 'first';
	const PREFER_LONGEST = 'longest';
	const PREFER_SHORTEST = 'shortest';

	private $preference = [];

	public function pushPreference($preference)
	{
		array_push($this->preference, $preference);
	}

	public function popPreference()
	{
		array_pop($this->preference);
	}

	public function getPreference()
	{
		return end($this->preference);
	}

	public function __construct($skipper = null, $case_sensitive = TRUE, $or_mode = self::PREFER_FIRST)
	{
		$this->pushSpacer($skipper);
		$this->pushCaseSensitivity($case_sensitive);
		$this->pushPreference($or_mode);
	}

}
