<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of TextParser
 *
 * @author Martijn
 */
class Text extends Parser {

	use CaseSensitiveTrait;

	private $text = null;
	private $length = null;

	public function __construct($text)
	{
		$this->text = $text;

		$this->length = mb_strlen($text);
		if ($this->length <= 0) {
			throw new \InvalidArgumentException('Empty argument');
		}
	}

	protected function parse(&$input, $offset, Context $context)
	{
		$this->pushCaseSensitivityToContext($context);

		$text = $context->handleCase($this->text);
		for ($c = 0; $c < $this->length; $c++) {
			if ($offset + $c >= mb_strlen($input) || $text[$c] != $context->handleCase($input[$offset + $c])) {
				$this->popCaseSensitivityFromContext($context);

				return $this->failure($input, $offset, $c);
			}
		}

		$this->popCaseSensitivityFromContext($context);

		return $this->success($input, $offset, $this->length);
	}

	public function __toString()
	{
		return '"' . $this->text . '"';
	}

}
