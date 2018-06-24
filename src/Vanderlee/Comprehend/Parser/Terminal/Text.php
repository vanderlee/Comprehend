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
			throw new \Exception('Empty argument');
		}
	}

	protected function parse(string &$in, int $offset, Context $context)
	{
		$this->pushCaseSensitivityToContext($context);

		$text = $context->handleCase($this->text);
		for ($c = 0; $c < $this->length; $c++) {
			if ($offset + $c >= mb_strlen($in) || $text[$c] != $context->handleCase($in[$offset + $c])) {
				$this->popCaseSensitivityFromContext($context);

				return $this->failure($in, $offset, $c);
			}
		}

		$this->popCaseSensitivityFromContext($context);

		return $this->success($in, $offset, $this->length);
	}

	public function __toString()
	{
		return '"' . $this->text . '"';
	}

}
