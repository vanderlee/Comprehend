<?php

use \vanderlee\comprehend\parser\terminal\Lexeme;
use \vanderlee\comprehend\parser\terminal\Char;
use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\structure\Repeat;
use \vanderlee\comprehend\match\Match;

/**
 * @group directive
 */
class ContextSpacingTest extends TestCase {

	/**
	 * @covers Lexeme
	 */
	public function testSequenceLexeme()
	{
		$scanner = new Repeat('-', 1);
		$parser = new Sequence('foo','bar');				
		$parser->spacing($scanner);
		
		$result = $parser->match('foo-bar');
		$this->assertResult(true, 7, $result);
	}

	/**
	 * @covers Lexeme
	 */
	public function testRepeatLexeme()
	{
		$scanner = new Repeat('-', 1);
		$parser = new Repeat('foo');
		$parser->spacing($scanner);
		
		$result = $parser->match('foo-foo-foo');
		$this->assertTrue($result->match, (string) $parser);
		$this->assertSame(11, $result->length, (string) $parser);
	}
	
	/**
	 * @covers Lexeme
	 */
	public function testOutsideSpacing()
	{
		$scanner = new Char(' ');
		$parser = new Sequence('foo', 'bar');
		$parser->spacing($scanner);
		
		$this->assertResult(true, 7, $parser->match('foo bar'));
		$this->assertResult(false, 0, $parser->match(' foo bar'));
		$this->assertResult(true, 7, $parser->match('foo bar '));
	}
	
	/**
	 * @covers Lexeme
	 */
	public function testStackSpacing()
	{
		$scanner = new Char('-');
		$inner = new Sequence('b', 'a', 'r');
		$outer = new Sequence('foo', $inner, 'baz');
		$outer->spacing($scanner);
		
		$this->assertResult(true, 11, $outer->match('foo-bar-baz'));
		$this->assertResult(true, 13, $outer->match('foo-b-a-r-baz'));
		$this->assertResult(false, 5, $outer->match('foo-b.a.r-baz'));

		$inner->spacing(false);	
		$this->assertResult(true, 11, $outer->match('foo-bar-baz'));
		$this->assertResult(false, 5, $outer->match('foo-b-a-r-baz'));
		$this->assertResult(false, 5, $outer->match('foo-b.a.r-baz'));

		$inner->spacing(true);
		$this->assertResult(true, 11, $outer->match('foo-bar-baz'));
		$this->assertResult(true, 13, $outer->match('foo-b-a-r-baz'));
		$this->assertResult(false, 5, $outer->match('foo-b.a.r-baz'));
		
		$inner->spacing('.');	
		$this->assertResult(true, 11, $outer->match('foo-bar-baz'));
		$this->assertResult(false, 5, $outer->match('foo-b-a-r-baz'));
		$this->assertResult(true, 13, $outer->match('foo-b.a.r-baz'));
	}

}
