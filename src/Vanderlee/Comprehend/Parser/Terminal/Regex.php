<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Matches regular expressions
 *
 * @author Martijn
 */
class Regex extends Parser {
	
	use CaseSensitiveTrait;

	private $pattern = null;

	public function __construct($pattern)
	{
		if (empty($pattern)) {
			throw new \Exception('Empty pattern');
		}
		
		if (@preg_match($pattern, null) === false) {
			throw new \Exception('Invalid pattern');			
		}
		
		$this->pattern = $pattern;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{		
		$this->pushCaseSensitivityToContext($context);
		$pattern = $this->pattern . ($context->isCaseSensitive() ? '' : 'i');
		$this->popCaseSensitivityFromContext($context);

		if (preg_match($pattern, $in, $m, 0, $offset) !== FALSE) {
			if (count($m) > 0 && mb_strlen($m[0]) > 0 && strpos($in, $m[0], $offset) == $offset) {
				return $this->success($in, $offset, mb_strlen($m[0]));
			}
		}
		
		return $this->failure($in, $offset);
	}
	
	public function __toString()
	{
		return $this->pattern;
	}

}
