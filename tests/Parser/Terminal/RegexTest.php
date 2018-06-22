<?php

use \vanderlee\comprehend\parser\terminal\Regex;

/**
 * @group terminal
 * @group parser
 */
class RegexTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Regex
	 */
	public function testEmpty()
	{		
		$this->expectExceptionMessage('Empty pattern');
		new Regex('');
	}

	/**
	 * @covers Regex
	 */
	public function testInvalidNonAlphanumericDelimiter()
	{		
		$this->expectExceptionMessage('Invalid pattern');
		new Regex('a');
	}

	/**
	 * @covers Regex
	 */
	public function testInvalidNoEndingDelimiter()
	{		
		$this->expectExceptionMessage('Invalid pattern');
		$s = new Regex('/');
	}
	
	/**
	 * @covers Regex
	 * @dataProvider regexData
	 */
	public function testRegex(Regex $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function regexData()
	{
		return [
			[new Regex('~[a-f]+~i'), 'abc', 0, true, 3],
			[new Regex('~[a-f]+~i'), 'abcz', 0, true, 3],
			[new Regex('~[a-f]+~i'), 'zabc', 0, false, 0],
			[new Regex('~[a-f]*~i'), 'zabc', 0, false, 0],
			[new Regex('~[a-f]+~i'), 'AbC', 0, true, 3],
			[new Regex('~[a-f]+~i'), 'zabc', 1, true, 3],
			[new Regex('~[a-f]+~'), 'abc', 0, true, 3],
			[new Regex('~[a-f]+~'), 'ABC', 0, false, 0],
			[(new Regex('~[a-f]+~'))->caseInsensitive(), 'ABC', 0, true, 3],
		];
	}

}
