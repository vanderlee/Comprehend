<?php

use \vanderlee\comprehend\parser\Stub;

/**
 * @group structure
 * @group parser
 */
class StubTest extends TestCase {

	/**
	 * @covers Stub::__construct
	 */
	public function testConstruct()
	{
		$parser = new Stub;
		$this->expectExceptionMessage('Missing parser');
		$parser->match('foo');
	}

	/**
	 * @covers Stub::__set
	 */
	public function testSetParser()
	{
		$parser = new Stub;
		$parser->parser = 'foo';
		$this->assertResult(true, 3, $parser->match('foo'));
	}

	/**
	 * @covers Stub::__set
	 */
	public function testSetOther()
	{
		$parser = new Stub;		
		$this->expectExceptionMessage('Property `foo` does not exist');
		$parser->foo = 'foo';
	}
	
	/**
	 * @covers Stub::__set
	 */
	public function testToString()
	{
		$parser = new Stub;
		$parser->parser = 'foo';
		$this->assertEquals('"foo"', (string) $parser);
	}

}
