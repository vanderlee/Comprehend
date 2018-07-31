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

	use CaseSensitiveTrait;

	private $set = null;
	private $in = true;

	/**
	 * Match any single character in the set or not in the set.
	 * 
	 * @param string $set
	 * @param bool $in Set to false to match only characters NOT in the set
	 * @throws \Exception
	 */
	public function __construct(string $set, $in = true)
	{
		if (mb_strlen($set) <= 0) {
			throw new \Exception('Empty set');
		}

		$this->set = count_chars($set, 3);
		$this->in = (bool) $in;
	}

	protected function parse(string &$in, int $offset, Context $context)
	{

		if ($offset >= mb_strlen($in)) {
			return $this->failure($in, $offset);
		}

		$this->pushCaseSensitivityToContext($context);

		if (strchr($context->handleCase($this->set), $context->handleCase($in[$offset])) !== FALSE) {
			$this->popCaseSensitivityFromContext($context);

			return $this->in ? $this->success($in, $offset, 1) : $this->failure($in, $offset);
		}

		$this->popCaseSensitivityFromContext($context);

		return $this->in ? $this->failure($in, $offset) : $this->success($in, $offset, 1);
	}

	public function __toString()
	{			
		return ($this->in ? '' : chr(0xAC)) . '( \'' . join('\' | \'', str_split($this->set)) . '\' )';
	}

}
