<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of Factory
 *
 * @author Martijn
 */
class Factory extends Parser {

	/** @var Parser */
	public $parser = null;

	public function __construct(Definition $definition, $arguments)
	{
		$parser = $definition->parser;
		if (!$parser instanceof Parser) {
			if (is_callable($parser)) {
				$parser = $parser(...$arguments);
			} else {
				throw new \Exception('Parser not defined');
			}
		}

		$this->parser = $parser;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		return $this->parser->parse($in, $offset, $context);
	}

	public function __toString()
	{
		return (string) $this->parser;
	}

}
