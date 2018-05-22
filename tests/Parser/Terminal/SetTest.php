<?php

use \vanderlee\comprehension\parser\terminal\Set;

/**
 * @group terminal
 * @group parser
 */
class SetTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Set
	 */
	public function testEmpty()
	{
		$this->expectExceptionMessage('Empty set');
		new Set('');
	}

	/**
	 * @covers Set
	 * @dataProvider setData
	 */
	public function testSet(Set $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function setData()
	{
		return [
			[new Set('a'), 'abc', 0, true, 1],
			[new Set('b'), 'abc', 0, false, 0],
			[new Set('az'), 'abc', 0, true, 1],
			[new Set('az'), 'b', 0, false, 0],
			[new Set('az'), 'z', 0, true, 1],
			[new Set('abc'), 'a', 0, true, 1],
			[new Set('abc'), 'b', 0, true, 1],
			[new Set('abc'), 'c', 0, true, 1],
			[new Set('abc'), 'za', 0, false, 0],
			[new Set('abc'), 'za', 1, true, 1],
		];
	}

}
