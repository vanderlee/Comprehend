<?php

use \vanderlee\comprehend\parser\terminal\End;

/**
 * @group structure
 * @group parser
 */
class EndTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers End->parse
	 * @dataProvider endData
	 */
	public function testEnd(End $parser, $input, $offset, $match, $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function endData()
	{
		return [
			[new End(), 'aa', 0, false, 0],
			[new End(), 'aa', 1, false, 0],
			[new End(), 'aa', 2, true, 0],
		];
	}

}
