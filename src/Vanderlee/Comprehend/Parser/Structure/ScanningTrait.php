<?php

namespace vanderlee\comprehend\parser\structure;

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\core\Context;

/**
 * Classes implementing this can scan
 * 
 * @author Martijn
 */
trait ScanningTrait {

	/**
	 * Parser used for scanning the text
	 * @var Parser 
	 */
	private $scanner = null;

	private function pushScannerToContext(Context $context)
	{
		if ($this->scanner) {
			$context->pushSkipper($this->scanner);
		}
	}

	private function popScannerFromContext(Context $context)
	{
		if ($this->scanner) {
			$context->popSkipper();
		}
	}

	public function setScanner(Parser $scanner)
	{
		$this->scanner = $scanner;
	}

}
