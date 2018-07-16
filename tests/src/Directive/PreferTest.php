<?php

use \vanderlee\comprehend\directive\Prefer;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\parser\structure\Choice;

/**
 * @group directive
 * @coversDefaultClass Prefer
 */
class PreferTest extends TestCase {

	/**
	 * @covers ::__construct
	 */
	public function testEmpty()
	{
		$this->expectExceptionMessage('Invalid preference');
		new Prefer(null, 'x');
	}	
	
	/**
	 * @covers ::match
	 * @dataProvider preferData
	 */
	public function testPrefer(Prefer $parser, string $input, int $offset, bool $match, int $length)
	{
		$this->assertResult($match, $length, $parser->match($input, $offset), (string) $parser);
	}

	public function preferData()
	{
		return [
			[new Prefer(Prefer::FIRST, new Choice('a', 'aa')), 'aa', 0, true, 1],
			[new Prefer(Prefer::FIRST, new Choice('aa', 'a')), 'aa', 0, true, 2],
			[new Prefer(Prefer::LONGEST, new Choice('a', 'aa')), 'aa', 0, true, 2],
			[new Prefer(Prefer::LONGEST, new Choice('aa', 'a')), 'aa', 0, true, 2],
			[new Prefer(Prefer::SHORTEST, new Choice('a', 'aa')), 'aa', 0, true, 1],
			[new Prefer(Prefer::SHORTEST, new Choice('aa', 'a')), 'aa', 0, true, 1],
		];
	}

}
