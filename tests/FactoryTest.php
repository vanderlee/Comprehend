<?php

use \vanderlee\comprehend\parser\Parser;
use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\structure\Repeat;
use \vanderlee\comprehend\parser\terminal\Set;
use \vanderlee\comprehend\parser\structure\Choice;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\match\Match;

use \vanderlee\comprehend\builder\Definition;
use \vanderlee\comprehend\builder\Ruleset;

/**
 * @group structure
 * @group parser
 */
class BuilderTest extends TestCase {
	const CSV_RECORD = [__CLASS__, 'makeCsvRecordParser'];
	const QUOTED_STRING = [__CLASS__, 'makeQuotedStringParser'];
	
	public static function makeQuotedStringParser($enclosures = '"') {
		if (mb_strlen($enclosures) === 1) {
			return new Sequence(new Set($enclosures), new Repeat(new Set($enclosures, false)), new Set($enclosures));
		} else {
			return new Choice(array_map(function($enclosure) {
				return new Sequence(new Set($enclosure), new Repeat(new Set($enclosure, false)), new Set($enclosure));
			}, str_split($enclosures)));
		}
	}
	
	public static function makeCsvRecordParser($item, $delimiter = ',') {	
		return new Sequence($item, new Repeat(new Sequence($delimiter, $item)));
	}

	public function testDefinition() {
		$definition = new Definition(self::CSV_RECORD);
	
		$List = $definition('x');		
		$this->assertResult(true, 5, $List('x,x,x'));
		
		$List = $definition->build('x');		
		$this->assertResult(true, 5, $List('x,x,x'));
	}

	public function testDefinitionSetParser() {
		$definition = (new Definition)->parser(self::CSV_RECORD);
		
		$List = $definition->build('x');		
		$this->assertResult(true, 5, $List('x,x,x'));
	}
	
	public function testRuleset()
	{
		$r = new Ruleset();
		$r->define('List', new Definition(self::CSV_RECORD));
		
		$List = $r->List('x');
		$this->assertResult(true, 5, $List('x,x,x'));
	}
	
	public function testQuotedList()
	{
		$qs = (new Definition(self::QUOTED_STRING))();
		$this->assertResult(true, 5, $qs('"foo"'));
		$this->assertResult(false, 5, $qs('"foo`'));
		
		$qs = (new Definition(self::QUOTED_STRING))('e');
		$this->assertResult(true, 4, $qs('emoe'));
		$this->assertResult(false, 3, $qs('emo'));
		
		$qs = (new Definition(self::QUOTED_STRING))('"/');
		$this->assertResult(true, 5, $qs('/foo/'));
		$this->assertResult(true, 5, $qs('"foo"'));
		$this->assertResult(false, 5, $qs('"foo/'));
		$this->assertResult(false, 5, $qs('/foo"'));
		$this->assertResult(true, 6, $qs('/foo"/'));
	}

}
