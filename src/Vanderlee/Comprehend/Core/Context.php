<?php

namespace vanderlee\comprehend\core;

use \vanderlee\comprehend\core\ArgumentsTrait;
use \vanderlee\comprehend\directive\Prefer;

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

	private $case_sensitivity = [];

	public function pushCaseSensitivity($case_sensitive = TRUE)
	{
		array_push($this->case_sensitivity, (bool) $case_sensitive);
	}

	public function popCaseSensitivity()
	{
		return array_pop($this->case_sensitivity);
	}

	public function isCaseSensitive()
	{
		return end($this->case_sensitivity);
	}

	// Helper
	public function handleCase($text)
	{
		return $this->isCaseSensitive() ? $text : mb_strtolower($text);
	}

	private $preference = [];

	private static function assertPreference($preference)
	{
		if (!in_array($preference, [
					Prefer::FIRST,
					Prefer::LONGEST,
					Prefer::SHORTEST,
				])) {
			throw new \Exception("Preference `{$preference}` not supported");
		}
	}

	public function pushPreference($preference)
	{
		self::assertPreference($preference);

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

	public function __construct($skipper = null, $case_sensitive = TRUE, $preference = Prefer::FIRST)
	{
		self::assertPreference($preference);

		$this->pushSpacer($skipper);
		$this->pushCaseSensitivity($case_sensitive);
		$this->pushPreference($preference);
	}

}
