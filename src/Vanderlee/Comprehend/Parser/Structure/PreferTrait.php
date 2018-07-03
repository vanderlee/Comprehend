<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Classes implementing this can scan
 * 
 * @author Martijn
 */
trait PreferTrait {

	/**
	 * Parser used for scanning the text
	 * @var Parser 
	 */
	private $preference = null;

	private function pushPrefererenceToContext(Context $context)
	{
		if ($this->preference) {
			$context->pushPreference($this->preference);
		}
	}

	private function popPreferenceFromContext(Context $context)
	{
		if ($this->preference) {
			$context->popPreference();
		}
	}

	public function setPreference(string $preference)
	{
		$this->preference = $preference;

		return $this;
	}

	public function preferShortest()
	{
		$this->preference = Context::PREFER_SHORTEST;

		return $this;
	}

	public function preferLongest()
	{
		$this->preference = Context::PREFER_LONGEST;

		return $this;
	}

	public function preferFirst()
	{
		$this->preference = Context::PREFER_FIRST;

		return $this;
	}

}
