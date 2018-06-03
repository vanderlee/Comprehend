<?php

use \vanderlee\comprehend\parser\structure\Not;

/**
 * @group structure
 * @group parser
 */
class NotTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Not->parse
	 * @dataProvider notData
	 */
	public function testNot(Not $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function notData()
	{
		return [
			[new Not('a'), 'a', 0, false, 1],
			[new Not('aa'), 'aa', 0, false, 2],
			[new Not('ab'), 'aa', 0, true, 0],
			[new Not('a'), 'b', 0, true, 0],
			[new Not('ab'), 'ac', 0, true, 0],
			[new Not('a'), 'ab', 1, true, 0],
		];
	}

}
