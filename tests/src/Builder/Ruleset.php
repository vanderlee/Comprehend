<?php

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\structure\Repeat;
use \vanderlee\comprehend\parser\terminal\Set;
use \vanderlee\comprehend\parser\structure\Choice;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\match\Match;
use \vanderlee\comprehend\parser\terminal\Range;
use \vanderlee\comprehend\builder\Definition;
use \vanderlee\comprehend\builder\Ruleset;

/**
 * @group structure
 * @group parser
 */
class RulesetTest extends TestCase {

	const CSV_RECORD = [__CLASS__, 'makeCsvRecordParser'];

	public static function makeCsvRecordParser($item, $delimiter = ',')
	{
		return new Sequence($item, new Repeat(new Sequence($delimiter, $item)));
	}

	public function testRuleset()
	{
		$r = new Ruleset();
		$r->define('List', new Definition(self::CSV_RECORD));

		$List = $r->List('x');
		$this->assertResult(true, 5, $List('x,x,x'));
	}

}
