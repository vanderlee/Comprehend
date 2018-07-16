<?php

use \vanderlee\comprehend\match\Failure;

/**
 * @group match
 */
class FailureTest extends TestCase {

	/**
	 * @covers Failure::__construct
	 */
	public function testZero()
	{
		$failure = new Failure;

		$this->assertEquals(0, $failure->length);
		$this->assertFalse($failure->match);
		$this->assertEquals('Failed match at 0 characters', (string) $failure);
	}

	/**
	 * @covers Failure::__construct
	 */
	public function testLength()
	{
		$failure = new Failure(3);

		$this->assertEquals(3, $failure->length);
		$this->assertFalse($failure->match);
		$this->assertEquals('Failed match at 3 characters', (string) $failure);
	}

}
