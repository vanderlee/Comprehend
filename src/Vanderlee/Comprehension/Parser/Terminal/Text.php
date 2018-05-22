<?php

namespace vanderlee\comprehension\parser\terminal;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Description of TextParser
 *
 * @author Martijn
 */
class Text extends AbstractParser {
	
	private $text = null;
	private $length = null;

	public function __construct($text)
	{
		$this->text = $text;
		$this->length = mb_strlen($text);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$length = mb_strlen($this->text);
		
		if ($length <= 0) {
			return $this->createMismatch($in, $offset, self::INVALID_ARGUMENTS);
		}

		$text = $context->handleCase($this->text);
		for ($c = 0; $c < $length; $c++) {
			if ($offset + $c >= mb_strlen($in) || $text[$c] != $context->handleCase($in[$offset + $c])) {
				return $this->createMismatch($in, $offset, $c);
			}
		}
		
		return $this->createMatch($in, $offset, $length);
	}
	
	public function __toString()
	{
		return '"' . $this->text . '"';
	}

}
