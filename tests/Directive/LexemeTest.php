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
	public function testLexeme()
	{
		$scanner = new Repeat('-', 1);
		$parser = new Sequence('foo','bar');				
		$parser->setScanner($scanner);
		
		$result = $parser->match('foo-bar');
		$this->assertTrue($result->match, (string) $parser);
		$this->assertSame(7, $result->length, (string) $parser);
	}

}
