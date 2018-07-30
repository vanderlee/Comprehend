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

	public function testSetFunction()
	{
		$r = new Ruleset;

		$r->line = function($char) {
			return new Repeat($char);
		};
		$line = $r->line('x');
		$this->assertResult(true, 5, $line('xxxxx'));
	}

	public function testSetDefinition()
	{
		$r = new Ruleset;

		$r->line = new Definition(function($char) {
			return new Repeat($char);
		});
		
		$line = $r->line('x');
		$this->assertResult(true, 5, $line('xxxxx'));
	}

	public function testSetParser()
	{
		$r = new Ruleset;

		$r->line = new Repeat('x');
		
		$line = $r->line();
		$this->assertResult(true, 5, $line('xxxxx'));
	}

	public function testSetForwardFunction()
	{
		$r = new Ruleset;
		
		$line = $r->line('x');
		
		$r->line = function($char) {
			return new Repeat($char);
		};

		$this->assertResult(true, 5, $line('xxxxx'));
	}

	public function testSetForwardDefinition()
	{
		$r = new Ruleset;
		
		$line = $r->line('x');
		
		$r->line = new Definition(function($char) {
			return new Repeat($char);
		});

		$this->assertResult(true, 5, $line('xxxxx'));
	}

	public function testSetForwardParser()
	{
		$r = new Ruleset;
		
		$line = $r->line();
		
		$r->line = new Repeat('x');

		$this->assertResult(true, 5, $line('xxxxx'));
	}

	public function testSetAndGetFunction()
	{
		$r = new Ruleset;

		$r->line = function($char = 'x') {
			return new Repeat($char);
		};
		
		$line = $r->line;
		$this->assertResult(true, 5, $line('xxxxx'));
	}
	
	public function testSetAndGetParser()
	{
		$r = new Ruleset;

		$r->line = new Repeat('x');
		
		$line = $r->line;
		$this->assertResult(true, 5, $line('xxxxx'));
	}	

	public function testSetAndGetForwardFunction()
	{
		$r = new Ruleset;
		
		$line = $r->line;
		
		$r->line = function($char = 'x') {
			return new Repeat($char);
		};

		$this->assertResult(true, 5, $line('xxxxx'));
	}
	
	public function testSetAndGetForwardParser()
	{
		$r = new Ruleset;
		
		$line = $r->line;
		
		$r->line = new Repeat('x');

		$this->assertResult(true, 5, $line('xxxxx'));
	}	
}
