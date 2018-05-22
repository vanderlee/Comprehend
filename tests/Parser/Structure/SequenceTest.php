<?php

use \vanderlee\comprehension\parser\structure\Sequence;
use \vanderlee\comprehension\parser\terminal\Text;
use \vanderlee\comprehension\parser\terminal\Char;

/**
 * @group structure
 * @group parser
 */
class SequenceTest extends PHPUnit\Framework\TestCase {

	/**
	 * @covers Sequence
	 * @dataProvider sequenceData
	 */
	public function testSequence(Sequence $parser, string $input, int $offset, bool $match, int $length)
	{
		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function sequenceData()
	{
		return [
			[new Sequence('a'), 'a', 0, true, 1],
			[new Sequence('a'), 'aa', 0, true, 1],
			[new Sequence('a'), 'b', 0, false, 0],
			[new Sequence('a'), 'B', 0, false, 0],
			[new Sequence('abc'), 'abc', 0, true, 3],
			[new Sequence('a', 'b'), 'ab', 0, true, 2],
			[new Sequence('b', 'a'), 'ab', 0, false, 0],
			[new Sequence('a', 'a'), 'ab', 0, false, 1],
			[new Sequence('a'), '', 0, false, 0],
			[new Sequence(new Text('abc')), 'abc', 0, true, 3],
			[new Sequence(new Char('a')), 'abc', 0, true, 1],
			[new Sequence(new Char('a'), new Text('bc')), 'abc', 0, true, 3],
			[new Sequence(), '', 0, false, Sequence::INVALID_ARGUMENTS],
		];
	}

}
