<?php

use \vanderlee\comprehend\parser\structure\All;
use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\structure\Repeat;

/**
 * @group structure
 * @group parser
 */
class AllTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers All::__construct
	 */
	public function testLessThanTwoArguments()
	{
		$this->expectExceptionMessage('Less than 2 arguments provided');
		new All('a');
	}

	/**
	 * @covers All->parse
	 * @dataProvider allData
	 */
	public function testAll(All $parser, $input, $offset, $match, $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function allData()
	{
		return [
			[new All('a', 'b'), 'a', 0, false, 0],
			[new All('a', 'b'), 'b', 0, false, 0],
			[new All('a', 'a'), 'a', 0, true, 1],
			[new All('aa', 'a'), 'aa', 0, true, 1],
			[new All('a', 'aa'), 'aa', 0, true, 1],
			[new All('aa', 'ab'), 'aa', 0, false, 1],
			[new All('aa', new Sequence('a', 'a')), 'aa', 0, true, 2],
			[new All('aa', new Repeat('a', 0, 1)), 'aa', 0, true, 1],
			[new All('aa', new Repeat('a', 0, 2)), 'aa', 0, true, 2],
			[new All('aa', new Repeat('a', 1, 1)), 'aa', 0, true, 1],
			[new All('aa', new Repeat('a', 1, 2)), 'aa', 0, true, 2],
			[new All('aa', new Repeat('a', 2, 2)), 'aa', 0, true, 2],
			[new All('aa', new Repeat('a', 2, 3)), 'aaa', 0, true, 2],
			[new All('aa', new Repeat('a', 3, 3)), 'aaa', 0, true, 2],
		];
	}

}
