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
		$r->define('Csv', new Definition(self::CSV_RECORD));

		$Csv = $r->Csv('x');
		$this->assertResult(true, 5, $Csv('x,x,x'));
		
		$r->undefine('Csv');
		$this->expectExceptionMessage('No parser named `Csv` is defined');
		$Csv = $r->Csv('x');
	}

	public function testStaticRuleset()
	{
		Ruleset::define('Csv', new Definition(self::CSV_RECORD));

		$Csv = Ruleset::Csv('x');
		$this->assertResult(true, 5, $Csv('x,x,x'));
		
		Ruleset::undefine('Csv');
		$this->expectExceptionMessage('No parser named `Csv` is defined');
		$Csv = Ruleset::Csv('x');
	}

	public function testSet()
	{
		$r = new Ruleset;
		
		$r->Csv = new Definition(self::CSV_RECORD);		
				
		$Csv = $r->Csv('x');
		$this->assertResult(true, 5, $Csv('x,x,x'));
		
		$r->undefine('Csv');
		$this->expectExceptionMessage('No parser named `Csv` is defined');
		$Csv = $r->Csv('x');
	}	
	
	public function testForwardDefinition()
	{
		$r = new Ruleset;
		
		$r->line = function() use($r) { return new Repeat($r->lineChar()); };
		$r->lineChar = new Set('-=');		
		
		$line = $r->line();
		$this->assertResult(true, 5, $line('--=-='));
		$this->assertResult(true, 2, $line('=='));
	}	

}
