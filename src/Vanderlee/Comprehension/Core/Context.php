<?php

namespace vanderlee\comprehension\core;

/**
 * Maintains the current context of the parser chain
 *
 * @author Martijn
 */
class Context {

	private $skipper;

	public function pushSkipper($skipper = null)
	{
		array_push($this->skipper, $skipper === null ? null : ParserUtil::getParserArg($skipper));
	}

	public function popSkipper()
	{
		array_pop($this->skipper);
	}

	public function skip($in, $offset)
	{
		$skipper = end($this->skipper);
		if ($skipper instanceof \vanderlee\comprehension\parser\AbstractParser) {
			$match = $skipper->parse($in, $offset, new Context());
			if ($match->match) {
				return $match->length;
			}
		}
		return 0;
	}

	private $case_sensitive;

	public function pushCaseSensitive($case_sensitive = TRUE)
	{
		array_push($this->case_sensitive, (bool) $case_sensitive);
	}

	public function popCaseSensitive()
	{
		array_pop($this->case_sensitive);
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

	const OR_FIRST = 0x01;
	const OR_LONGEST = 0x02;
	const OR_SHORTEST = 0x03;

	private $or_mode;

	public function pushOrMode($or_mode)
	{
		array_push($this->or_mode, $or_mode);
	}

	public function popOrMode()
	{
		array_pop($this->or_mode);
	}

	public function getOrMode()
	{
		return end($this->or_mode);
	}

	public function __construct($skipper = null, $case_sensitive = TRUE, $or_mode = self::OR_FIRST)
	{
		$this->skipper = array();
		$this->case_sensitive = array();
		$this->or_mode = array();

		$this->pushSkipper($skipper);
		$this->pushCaseSensitive($case_sensitive);
		$this->pushOrMode($or_mode);
	}

}
