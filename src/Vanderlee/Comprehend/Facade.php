<?php

namespace vanderlee\comprehend;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\structure\Choice;
use \vanderlee\comprehend\parser\terminal\Text;

/**
 * Description of Facade
 *
 * @author Martijn
 */
class Facade extends Parser {

	/**
	 * The entire parser tree root node
	 * @var Parser
	 */
	private $parser = null;

	/**
	 * The most recent parser added to the tree by the user (excluding parsers
	 * generated by the Facade itself).
	 * @var Parser
	 */
	private $current = null;
	
	/**
	 * The most recent parser added to the tree by the user (excluding parsers
	 * generated by the Facade itself).
	 * @var Parser
	 */
	private $stack = [];

	protected function parse(string &$in, int $offset, Context $context)
	{
		$this->parser = reset($this->stack);
		
		if ($this->parser === null) {
			return new \Exception('Too few parsers on stack');
		}

		$match = $this->parser->parse($in, $offset, $context);

		if ($match->match) {
			return $this->success($in, $offset, $match->length, $match);
		} else {
			return $this->failure($in, $offset, $match->length, $match);
		}
	}

	public static function __callStatic(string $name, array $arguments)
	{
		$method = "composite_$name";

		if (!method_exists(Facade::class, $method)) {
			throw new \Exception("Composite method for `{$name}` operation not available");
		}

		$facade = new Facade();

		call_user_func_array([$facade, "composite_$name"], $arguments);

		return $facade;
	}

	public function __call(string $name, array $arguments)
	{
		$method = "composite_$name";

		if (!method_exists($this, $method)) {
			throw new \Exception("Composite method for `{$name}` operation not available");
		}

		call_user_func_array([$this, $method], $arguments);

		return $this;
	}

	private function composite($parser)
	{
		$top = end($this->stack);
		if ($top instanceof Prefer) {
			$top->add($parser);
			$this->current = $parser;
		} elseif ($top instanceof Sequence) {
			$top->add($parser);
		} elseif ($top === false) {
			$this->stack[] = $parser;
		} else {
			$this->stack[] = new Sequence($this->current, $parser);
		}

		$this->current = $parser;

		return $this;
	}

	private function composite_text($text)
	{
		return $this->composite(new Text($text));
	}

	private function composite_or()
	{
		// track back up to next 'or'
		
		$this->current = $this->parser = new Choice($this->parser);
		
		$this->stack[] = $this->parser;

		return $this;
	}

	public function __toString()
	{
		return (string) $this->parser;
	}

}