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
	
	/**
	 * @covers Ruleset::define
	 * @covers Ruleset::defined
	 * @covers Ruleset::undefine
	 */
	public function testStaticDefine()
	{
		// Function
		Ruleset::define('line', function($char) {
			return new Repeat($char);
		});
		$this->assertTrue(Ruleset::defined('line'));
		$line = Ruleset::line('x');
		$this->assertResult(true, 5, $line('xxxxx'));
		
		Ruleset::undefine('line');		
		$this->assertFalse(Ruleset::defined('line'));
		
		// Definition
		Ruleset::define('line', new Definition(function($char) {
			return new Repeat($char);
		}));
		$this->assertTrue(Ruleset::defined('line'));
		$line = Ruleset::line('x');
		$this->assertResult(true, 5, $line('xxxxx'));
		
		Ruleset::undefine('line');		
		$this->assertFalse(Ruleset::defined('line'));
		
		// Parser
		Ruleset::define('line', new Repeat('x'));
		$this->assertTrue(Ruleset::defined('line'));
		$line = Ruleset::line();
		$this->assertResult(true, 5, $line('xxxxx'));
		
		Ruleset::undefine('line');		
		$this->assertFalse(Ruleset::defined('line'));
	}	
	
	/**
	 * @covers Ruleset->define
	 * @covers Ruleset->defined
	 * @covers Ruleset->undefine
	 */
	public function testInstanceDefine()
	{
		$r = new Ruleset;
		
		// Function
		$r->define('line', function($char) {
			return new Repeat($char);
		});
		$this->assertTrue($r->defined('line'));
		$line = $r->line('x');
		$this->assertResult(true, 5, $line('xxxxx'));
		
		$r->undefine('line');		
		$this->assertFalse($r->defined('line'));
		
		// Definition
		$r->define('line', new Definition(function($char) {
			return new Repeat($char);
		}));
		$this->assertTrue($r->defined('line'));
		$line = $r->line('x');
		$this->assertResult(true, 5, $line('xxxxx'));
		
		$r->undefine('line');		
		$this->assertFalse($r->defined('line'));
		
		// Parser
		$r->define('line', new Repeat('x'));
		$this->assertTrue($r->defined('line'));
		$line = $r->line();
		$this->assertResult(true, 5, $line('xxxxx'));
		
		$r->undefine('line');		
		$this->assertFalse($r->defined('line'));
	}	
	
	/**
	 * @covers Ruleset::call
	 * @covers Ruleset::set
	 */
	public function testStaticDefinePrivateMethods()
	{
		// set
		Ruleset::define('set', new Repeat('x'));
		$this->assertTrue(Ruleset::defined('set'));
		$line = Ruleset::set();
		$this->assertResult(true, 5, $line('xxxxx'));
		
		// call
		Ruleset::define('call', new Repeat('x'));
		$this->assertTrue(Ruleset::defined('call'));
		$line = Ruleset::call();
		$this->assertResult(true, 5, $line('xxxxx'));
	}
	
	/**
	 * @covers Ruleset::call
	 * @covers Ruleset::set
	 */
	public function testStaticDefineReserved()
	{
		// define
		$this->expectExceptionMessage('Cannot define reserved name `define`');
		Ruleset::define('define', new Repeat('x'));
	}
	
}
