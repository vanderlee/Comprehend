<?php

use \vanderlee\comprehend\Facade;

/**
 * @group structure
 * @group parser
 */
class FacadeTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Facade::__construct
	 */
	public function testText()
	{
		$parser = Facade::text('a');
		
		$result = $parser->match('a');
		$this->assertSame(true, $result->match, (string) $parser);
		$this->assertSame(1, $result->length, (string) $parser);
	}

	/**
	 * @covers Facade::__construct
	 */
	public function testSequence()
	{
		$parser = Facade::text('a')->text('b');
		
		$result = $parser->match('ab');
		$this->assertSame(true, $result->match, (string) $parser);
		$this->assertSame(2, $result->length, (string) $parser);
	}

	/**
	 * @covers Facade::__construct
	 */
	public function testOr()
	{
		$parser = Facade::text('a')->text('b')->or()->text('c')->text('d');
		echo $parser;
		
		$result = $parser->match('ab');
		$this->assertSame(true, $result->match, (string) $parser);
		$this->assertSame(2, $result->length, (string) $parser);
		
		$result = $parser->match('cd');
		$this->assertSame(true, $result->match, (string) $parser);
		$this->assertSame(2, $result->length, (string) $parser);
	}

}
