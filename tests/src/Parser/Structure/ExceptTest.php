<?php

use \vanderlee\comprehend\parser\structure\Except;

/**
 * @group structure
 * @group parser
 */
class ExceptTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Except->parse
	 * @dataProvider exceptData
	 */
	public function testExcept(Except $parser, $input, $offset, $match, $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function exceptData()
	{
		return [
			[new Except('a', 'aa'), '', 0, false, 0],
			[new Except('a', 'aa'), 'aa', 0, false, 1],
			[new Except('aa', 'a'), 'aa', 0, false, 1],
			[new Except('a', 'b'), 'a', 0, true, 1],
			[new Except('a', 'b'), 'ab', 0, true, 1],
			[new Except('a', 'b'), 'ab', 1, false, 0],
			[new Except('a', 'b'), 'ba', 1, true, 1],
			[new Except('a', 'b'), 'b', 0, false, 0],
		];
	}

}
