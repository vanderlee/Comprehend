<?php

use \vanderlee\comprehension\parser\terminal\Char;

/**
 * @group terminal
 * @group parser
 */
class CharTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Char
	 */
	public function testEmpty()
	{
		$this->expectExceptionMessage("Empty argument");
		new Char('');
	}

	/**
	 * @covers Char
	 */
	public function testTooLong()
	{
		$this->expectExceptionMessage("Non-character argument");
		new Char('aa');
	}

	/**
	 * @covers Char
	 * @dataProvider charData
	 */
	public function testChar(Char $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->parse($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function charData()
	{
		return [
			[new Char('a'), 'a', 0, true, 1],
			[new Char('a'), 'aa', 0, true, 1],
			[new Char('a'), 'A', 0, false, 0],
			[new Char('A'), 'a', 0, false, 0],
			[new Char('a'), 'b', 0, false, 0],
			[new Char('a'), '', 0, false, 0],
			[new Char('a'), 'ba', 0, false, 0],
			[new Char('a'), 'ba', 1, true, 1],
			[new Char(ord('a')), 'a', 0, true, 1],
		];
	}

}
