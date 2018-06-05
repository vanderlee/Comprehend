<?php

use \vanderlee\comprehend\parser\terminal\Lexeme;
use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\structure\Repeat;

/**
 * @group directive
 */
class LexemeTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Lexeme
	 */
	public function testSequenceLexeme()
	{
		$scanner = new Repeat('-', 1);
		$parser = new Sequence('foo','bar');				
		$parser->setScanner($scanner);
		
		$result = $parser->match('foo-bar');
		$this->assertTrue($result->match, (string) $parser);
		$this->assertSame(7, $result->length, (string) $parser);
	}

	/**
	 * @covers Lexeme
	 */
	public function testRepeatLexeme()
	{
		$scanner = new Repeat('-', 1);
		$parser = new Repeat('foo');
		$parser->setScanner($scanner);
		
		$result = $parser->match('foo-foo-foo');
		$this->assertTrue($result->match, (string) $parser);
		$this->assertSame(11, $result->length, (string) $parser);
	}

}
