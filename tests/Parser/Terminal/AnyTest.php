<?php

use \vanderlee\comprehension\parser\terminal\Any;

/**
 * @group terminal
 * @group parser
 */
class AnyTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Any
	 * @dataProvider anyData
	 */
	public function testAny(Any $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function anyData()
	{
		return [
			[new Any(), 'a', 0, true, 1],
			[new Any(), '', 0, false, 0],
			[new Any(), ' ', 0, true, 1],
			[new Any(), 'a', 1, false, 0],
			[new Any(), 'aa', 1, true, 1],
		];
	}

}
