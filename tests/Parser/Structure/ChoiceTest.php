<?php

use \vanderlee\comprehension\parser\structure\Choice;
use \vanderlee\comprehension\parser\terminal\Text;
use \vanderlee\comprehension\core\Context;

/**
 * @group structure
 * @group parser
 */
class ChoiceTest extends PHPUnit\Framework\TestCase {

	/**
	 * @param Choice $parser
	 * @param int $flag
	 * @param string $input
	 * @param int $offset
	 * @param bool $match
	 * @param int $length
	 * 
	 * @covers Choice
	 * @dataProvider choiceData
	 */
	public function testChoice(Choice $parser, int $flag, string $input, int $offset, bool $match, int $length)
	{
		if ($flag) {
			$parser = new \vanderlee\comprehension\directive\Choice($parser, $flag);
		}

		$result = $parser->match($input, $offset);

		$this->assertSame($match, $result->match, (string) $parser);
		$this->assertSame($length, $result->length, (string) $parser);
	}

	public function choiceData()
	{
		return [
			[new Choice('a', 'b'), 0, '', 0, false, 0],
			[new Choice('a', 'b'), 0, 'a', 0, true, 1],
			[new Choice('a', 'b'), 0, 'b', 0, true, 1],
			[new Choice('a', 'b'), 0, 'c', 0, false, 0],
			[new Choice('a', 'b'), 0, 'za', 0, false, 0],
			[new Choice('a', 'b'), 0, 'za', 1, true, 1],
			[new Choice('a', 'b'), Context::OR_FIRST, '', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_FIRST, 'a', 0, true, 1],
			[new Choice('a', 'b'), Context::OR_FIRST, 'b', 0, true, 1],
			[new Choice('a', 'b'), Context::OR_FIRST, 'c', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_FIRST, 'za', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_FIRST, 'za', 1, true, 1],
			[new Choice('a', 'ab'), Context::OR_FIRST, 'ab', 0, true, 1],
			[new Choice('ab', 'a'), Context::OR_FIRST, 'ab', 0, true, 2],
			[new Choice('abc', 'aaa'), Context::OR_FIRST, 'ab', 0, false, 2],
			[new Choice('aaa', 'abc'), Context::OR_FIRST, 'ab', 0, false, 2],
			[new Choice('a', 'b'), Context::OR_LONGEST, '', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_LONGEST, 'a', 0, true, 1],
			[new Choice('a', 'b'), Context::OR_LONGEST, 'b', 0, true, 1],
			[new Choice('a', 'b'), Context::OR_LONGEST, 'c', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_LONGEST, 'za', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_LONGEST, 'za', 1, true, 1],
			[new Choice('a', 'ab'), Context::OR_LONGEST, 'ab', 0, true, 2],
			[new Choice('ab', 'a'), Context::OR_LONGEST, 'ab', 0, true, 2],
			[new Choice('abc', 'aaa'), Context::OR_LONGEST, 'ab', 0, false, 2],
			[new Choice('aaa', 'abc'), Context::OR_LONGEST, 'ab', 0, false, 2],
			[new Choice('a', 'b'), Context::OR_SHORTEST, '', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_SHORTEST, 'a', 0, true, 1],
			[new Choice('a', 'b'), Context::OR_SHORTEST, 'b', 0, true, 1],
			[new Choice('a', 'b'), Context::OR_SHORTEST, 'c', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_SHORTEST, 'za', 0, false, 0],
			[new Choice('a', 'b'), Context::OR_SHORTEST, 'za', 1, true, 1],
			[new Choice('a', 'ab'), Context::OR_SHORTEST, 'ab', 0, true, 1],
			[new Choice('ab', 'a'), Context::OR_SHORTEST, 'ab', 0, true, 1],
			[new Choice('abc', 'aaa'), Context::OR_SHORTEST, 'ab', 0, false, 1],
			[new Choice('aaa', 'abc'), Context::OR_SHORTEST, 'ab', 0, false, 1],
				//@todo all things being equal, prefer first.
		];
	}

	public function testResultAs()
	{
		$parser = (new Choice(
				(new Text('a'))->resultAs('valueA'), (new Text('b'))->resultAs('valueB')
				))->resultAs('choice');

		$match = $parser->match('a');
		$this->assertTrue($match->hasResult('choice'));
		$this->assertEquals('a', $match->getResult('choice'));
		$this->assertTrue($match->hasResult('valueA'));
		$this->assertEquals('a', $match->getResult('valueA'));
		$this->assertFalse($match->hasResult('valueB'));
		$this->assertEquals(null, $match->getResult('valueB'));

		$match = $parser->match('b');
		$this->assertTrue($match->hasResult('choice'));
		$this->assertEquals('b', $match->getResult('choice'));
		$this->assertFalse($match->hasResult('valueA'));
		$this->assertEquals(null, $match->getResult('valueA'));
		$this->assertTrue($match->hasResult('valueB'));
		$this->assertEquals('b', $match->getResult('valueB'));

		$match = $parser->match('c');
		$this->assertFalse($match->hasResult('choice'));
		$this->assertEquals(null, $match->getResult('choice'));
		$this->assertFalse($match->hasResult('valueA'));
		$this->assertEquals(null, $match->getResult('valueA'));
		$this->assertFalse($match->hasResult('valueB'));
		$this->assertEquals(null, $match->getResult('valueB'));
	}

	public function testAssignTo()
	{
		$a = $b = $choice = null;

		$parser = (new Choice(
				(new Text('a'))->assignTo($a), (new Text('b'))->assignTo($b)
				))->assignTo($choice);

		$a = $b = $choice = null;
		$match = $parser->match('a');
		$this->assertTrue($match->match);
		$this->assertEquals('a', $choice);
		$this->assertEquals('a', $a);
		$this->assertEquals(null, $b);

		$a = $b = $choice = null;
		$match = $parser->match('b');
		$this->assertTrue($match->match);
		$this->assertEquals('b', $choice);
		$this->assertEquals(null, $a);
		$this->assertEquals('b', $b);

		$a = $b = $choice = null;
		$match = $parser->match('c');
		$this->assertFalse($match->match);
		$this->assertEquals(null, $choice);
		$this->assertEquals(null, $a);
		$this->assertEquals(null, $b);
	}

}
