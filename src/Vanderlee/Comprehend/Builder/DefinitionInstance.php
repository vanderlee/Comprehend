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
class DefinitionInstance extends Parser {

	/**
	 * @var Parser
	 */
	public $parser = null;

	/**
	 * @var callable
	 */
	public $validator = null;
	
	/**
	 * @var Definition
	 */
	private $definition = null;
	private $arguments = null;

	public function __construct(Definition &$definition, array $arguments = [])
	{
		$this->definition = $definition;
		$this->arguments = $arguments;				
	}
	
	private function build() {
		if ($this->parser === null) {		
			$this->parser = $this->definition->generator;
			if (!$this->parser instanceof Parser) {
				if (is_callable($this->parser)) {
					$this->parser = ($this->parser)(...$this->arguments);
				} else {
					throw new \Exception('Parser not defined');
				}
			}

			$this->validator = $this->definition->validator;
		}
	}

	protected function parse(string &$in, int $offset, Context $context)
	{	
		$this->build();
		
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
