<?php

namespace vanderlee\comprehend\directive;

use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\ArgumentsTrait;

/**
 * Description of LexemeDirective
 *
 * @author Martijn
 */
class Space extends Parser {

	use ArgumentsTrait;

	/**
	 * @var \vanderlee\comprehend\parser\Parser
	 */
	private $spacer = null;

	/**
	 * @var \vanderlee\comprehend\parser\Parser
	 */
	private $parser = null;

	/**
	 * Set (or disable) a spacer for the parser
	 * 
	 * @param Parser|string|int|null $spacer
	 * @param Parser|string|int $parser
	 */
	public function __construct($spacer = null, $parser)
	{
		$this->spacer = $spacer === null ? null : self::getArgument($spacer);
		$this->parser = self::getArgument($parser);
	}

	protected function parse(&$input, $offset, Context $context)
	{
		$context->pushSpacer($this->spacer);
		$match = $this->parser->parse($input, $offset, $context);
		$context->popSpacer();

		return $match;
	}
	
	public function __toString()
	{
		return (string) $this->parser;
	}

}
