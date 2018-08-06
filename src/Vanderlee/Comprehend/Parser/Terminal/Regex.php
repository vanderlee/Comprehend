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

    /**
     * @var string|null
     */
	private $pattern = null;

	public function __construct($pattern)
	{
		if (empty($pattern)) {
			throw new \InvalidArgumentException('Empty pattern');
		}
		
		if (@preg_match($pattern, null) === false) {
			throw new \InvalidArgumentException('Invalid pattern');
		}
		
		$this->pattern = $pattern;
	}

	protected function parse(&$input, $offset, Context $context)
	{		
		$this->pushCaseSensitivityToContext($context);
		$pattern = $this->pattern . ($context->isCaseSensitive() ? '' : 'i');
		$this->popCaseSensitivityFromContext($context);

		if (preg_match($pattern, $input, $m, 0, $offset) !== FALSE) {
			if (count($m) > 0 && mb_strlen($m[0]) > 0 && strpos($input, $m[0], $offset) == $offset) {
				return $this->success($input, $offset, mb_strlen($m[0]));
			}
		}
		
		return $this->failure($input, $offset);
	}
	
	public function __toString()
	{
		return (string) $this->pattern;
	}

}
