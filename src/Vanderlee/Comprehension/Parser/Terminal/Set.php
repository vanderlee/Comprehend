<?php

namespace vanderlee\comprehension\parser\terminal;

use \vanderlee\comprehension\parser\AbstractParser;
use \vanderlee\comprehension\core\Context;

/**
 * Description of SetParser
 *
 * @author Martijn
 */
class Set extends AbstractParser {

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

	protected function doParse(string &$in, int $offset, Context $context)
	{

		if ($offset >= mb_strlen($in)) {
			return $this->createMismatch($in, $offset);
		}

		if (strchr($context->handleCase($this->set), $context->handleCase($in[$offset])) !== FALSE) {
			return $this->createMatch($in, $offset, 1);
		}

		return $this->createMismatch($in, $offset);
	}

	public function __toString()
	{
		return '( \'' . join('\' | \'', str_split($this->set)) . '\' )';
	}

}
