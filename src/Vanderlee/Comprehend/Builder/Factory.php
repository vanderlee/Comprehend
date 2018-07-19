<?php

namespace vanderlee\comprehend\builder;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\match\Success;

/**
 * Description of Factory
 *
 * @author Martijn
 */
class Factory extends Parser {

	/**
	 * @var Parser
	 */
	public $parser = null;

	/**
	 * @var callable
	 */
	public $validator = null;

	public function __construct(Definition $definition, $arguments)
	{
		$this->parser = $definition->parser;
		if (!$this->parser instanceof Parser) {
			if (is_callable($this->parser)) {
				$this->parser = ($this->parser)(...$arguments);
			} else {
				throw new \Exception('Parser not defined');
			}
		}

		$this->validator = $definition->validator;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$match = $this->parser->parse($in, $offset, $context);

		if ($match instanceof Success && $this->validator) {
			$results = $match->getResults();
			$text = substr($in, $offset, $match->length);

			if (!($this->validator)($text, $results)) {
				$match = $this->failure($in, $offset, $match->length);
			}
		}

		return $match;
	}

	public function __toString()
	{
		return (string) $this->parser;
	}

}
