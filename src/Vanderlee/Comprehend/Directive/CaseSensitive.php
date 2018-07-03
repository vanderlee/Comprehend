<?php

namespace vanderlee\comprehend\directive;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\core\ArgumentsTrait;

/**
 * Description of CaseDirective
 *
 * @author Martijn
 */
class CaseSensitive extends Parser {

	use ArgumentsTrait;

	/**
	 * @var Parser
	 */
	private $parser = null;

	/**
	 * @var bool
	 */
	private $sensitivity = null;

	/**
	 * 
	 * @param Parser|string|integer $parser
	 * @param bool $sensitivity
	 */
	public function __construct(bool $sensitivity, $parser)
	{
		$this->parser = self::getArgument($parser);
		$this->sensitivity = (bool) $sensitivity;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$context->pushCaseSensitivity($this->sensitivity);
		$match = $this->parser->parse($in, $offset, $context);
		$context->popCaseSensitivity();

		return $match;
	}

	public function __toString()
	{
		return ($this->sensitivity ? 'case' : 'no-case') . '( ' . $this->parser . ' )';
	}

}
