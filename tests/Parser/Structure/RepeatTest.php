<?php

use \vanderlee\comprehend\parser\structure\Repeat;
use \vanderlee\comprehend\parser\terminal\Text;
use \vanderlee\comprehend\parser\terminal\Char;

/**
 * @group structure
 * @group parser
 */
class RepeatTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Repeat::__construct
	 */
	public function testEmpty()
	{
		$this->expectExceptionMessage('Invalid repeat range specified');
		new Repeat('a', 2, 1);
	}
	
	/**
	 * @covers Repeat->doParse
	 * @dataProvider repeatData
	 */
	public function testRepeat(Repeat $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function repeatData()
	{
		return [
			[new Repeat('a', 2, 4), 'b', 0, false, 0],
			
			[new Repeat('a', 2, 4), '', 0, false, 0],
			[new Repeat('a', 2, 4), 'a', 0, false, 1],
			[new Repeat('a', 2, 4), 'aa', 0, true, 2],
			[new Repeat('a', 2, 4), 'aaa', 0, true, 3],
			[new Repeat('a', 2, 4), 'aaaa', 0, true, 4],
			[new Repeat('a', 2, 4), 'aaaaa', 0, true, 4],
			
			[new Repeat('a', 0, 1), '', 0, true, 0],
			[new Repeat('a', 0, 1), 'a', 0, true, 1],
			[new Repeat('a', 0, 1), 'aa', 0, true, 1],
			[new Repeat('a', 0, 2), 'aa', 0, true, 2],
			[new Repeat('a', 0, 2), 'aaa', 0, true, 2],
			[new Repeat('a', 1, 1), 'aaa', 0, true, 1],
			[new Repeat('a', 1, 2), 'aaa', 0, true, 2],
			
			[new Repeat('a', 2, 2), 'a', 0, false, 1],
			[new Repeat('a', 2, 2), 'aa', 0, true, 2],
			[new Repeat('a', 2, 2), 'aaa', 0, true, 2],
			
			[new Repeat('a', 0), '', 0, true, 0],
			[new Repeat('a', 0), 'a', 0, true, 1],
			[new Repeat('a', 2), 'a', 0, false, 1],
			[new Repeat('a', 2), 'aa', 0, true, 2],
			[new Repeat('a', 2), 'aaa', 0, true, 3],
			[new Repeat('a', 2), 'aaaa', 0, true, 4],
			
			[new Repeat('ab', 2, 2), 'ab', 0, false, 2],
			[new Repeat('ab', 2, 2), 'aba', 0, false, 2],
			[new Repeat('ab', 2, 2), 'abab', 0, true, 4],
			[new Repeat('ab', 2, 2), 'ababa', 0, true, 4],
			[new Repeat('ab', 2, 2), 'ababab', 0, true, 4],
		];
	}

}
