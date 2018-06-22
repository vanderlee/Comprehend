<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of CharParser
 *
 * @author Martijn
 */
class Char extends Parser {
	
	use CaseSensitiveTrait;
	
	private $character = null;

	public function __construct($character)
	{
		$this->character = self::parseCharacter($character);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		if ($offset >= mb_strlen($in)) {
			return $this->failure($in, $offset);
		}

		$this->pushCaseSensitivityToContext($context);
		
		if ($context->handleCase($in[$offset]) == $context->handleCase($this->character)) {
			$this->popCaseSensitivityFromContext($context);
			
			return $this->success($in, $offset, 1);
		}		
		
		$this->popCaseSensitivityFromContext($context);

		return $this->failure($in, $offset);
	}

	public function __toString()
	{
		return '\'' . $this->character . '\'';
	}

}
