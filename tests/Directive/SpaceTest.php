<?php

use \vanderlee\comprehend\directive\Space;
use \vanderlee\comprehend\core\Context;
use \vanderlee\comprehend\parser\structure\Sequence;

/**
 * @group directive
 * @coversDefaultClass Space
 */
class SpaceTest extends TestCase {	
	/**
	 * @covers ::match
	 * @dataProvider spaceData
	 */
	public function testSpace(Space $parser, string $input, int $offset, bool $match, int $length)
	{
		$this->assertResult($match, $length, $parser->match($input, $offset), (string) $parser);
	}

	public function spaceData()
	{		
		return [
			[new Space('-', new Sequence('a', 'b')), 'ab', 0, true, 2],
			[new Space('-', new Sequence('a', 'b')), 'a-b', 0, true, 3],
			[new Space('-', new Sequence('a', 'b')), 'a-b-', 0, true, 3],
			[new Space('-', new Sequence('a', 'b')), '-a-b-', 0, false, 0],
			[new Space('-', new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')), 'a-b-c-d', 0, false, 3],
			[new Space('-', new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')), 'a-bc-d', 0, true, 6],
			[new Space('-', new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')), 'abcd', 0, true, 4],
			[new Space('-', new Sequence('a', new Space(null, new Sequence('b', 'c')), 'd')), 'ab-cd', 0, false, 2],
		];
	}

}
