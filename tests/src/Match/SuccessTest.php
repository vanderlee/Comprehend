<?php

use \vanderlee\comprehend\match\Success;

/**
 * @group match
 */
class SuccessTest extends TestCase {

	/**
	 * @covers Success::__construct
	 */
	public function testZero()
	{
		$Success = new Success;

		$this->assertEquals(0, $Success->length);
		$this->assertTrue($Success->match);
		$this->assertEquals('Successfully matched 0 characters', (string) $Success);
	}

	/**
	 * @covers Success::__construct
	 */
	public function testLength()
	{
		$Success = new Success(3);

		$this->assertEquals(3, $Success->length);
		$this->assertTrue($Success->match);
		$this->assertEquals('Successfully matched 3 characters', (string) $Success);
	}

}
