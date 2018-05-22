<?php

namespace vanderlee\comprehension\parser\terminal;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Description of CharParser
 *
 * @author Martijn
 */
class Char extends AbstractParser {

	private $character = null;

	public function __construct($character)
	{
		$this->character = self::parseCharacter($character);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		if ($offset >= mb_strlen($in)) {
			return $this->createMismatch($in, $offset);
		}

		if ($context->handleCase($in[$offset]) == $context->handleCase($this->character)) {
			return $this->createMatch($in, $offset, 1);
		}

		return $this->createMismatch($in, $offset);
	}

	public function __toString()
	{
		return '\'' . $this->character . '\'';
	}

}
