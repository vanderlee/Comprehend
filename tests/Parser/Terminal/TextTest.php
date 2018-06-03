<?php

use \vanderlee\comprehend\parser\terminal\Text;

class TextTest extends PHPUnit\Framework\TestCase {

	/**
	 * @group terminal
	 * @group parser
	 * @covers Text
	 * @dataProvider textData
	 */
	public function testText(Text $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function textData()
	{
		return [
			[new Text('foo'), 'foo', 0, true, 3],
			[new Text('foo'), 'foobar', 0, true, 3],
			[new Text('FOO'), 'foo', 0, false, 0],
			[new Text('foo'), 'FOO', 0, false, 0],
			[new Text('foo'), 'faa', 0, false, 1],
			[new Text('bar'), 'foobar', 0, false, 0],
			[new Text('bar'), 'foobar', 3, true, 3],
			[new Text('bar'), 'foobaz', 3, false, 2],
			[new Text(''), '', 0, false, Text::INVALID_ARGUMENTS],
			[new Text(''), 'foo', 0, false, Text::INVALID_ARGUMENTS],
		];
	}

}
