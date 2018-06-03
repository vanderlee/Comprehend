<?php

namespace vanderlee\comprehend\parser\terminal;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Description of SetParser
 *
 * @author Martijn
 */
class Set extends Parser {

	private $set = null;

	/**
	 * 
	 * @param string $set
	 * @throws \Exception
	 */
	public function __construct(string $set)
	{
		if (mb_strlen($set) <= 0) {
			throw new \Exception('Empty set');
		}

		$this->set = count_chars($set, 3);
	}

	protected function parse(string &$in, int $offset, Context $context)
	{

		if ($offset >= mb_strlen($in)) {
			return $this->failure($in, $offset);
		}

		if (strchr($context->handleCase($this->set), $context->handleCase($in[$offset])) !== FALSE) {
			return $this->success($in, $offset, 1);
		}

		return $this->failure($in, $offset);
	}

	public function __toString()
	{
		return '( \'' . join('\' | \'', str_split($this->set)) . '\' )';
	}

}
