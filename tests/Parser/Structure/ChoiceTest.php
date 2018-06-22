<?php

use \vanderlee\comprehend\parser\structure\Choice;
use \vanderlee\comprehend\parser\terminal\Text;
use \vanderlee\comprehend\core\Context;

/**
 * @group structure
 * @group parser
 */
class ChoiceTest extends PHPUnit\Framework\TestCase {

	/**
	 * @param Choice $parser
	 * @param string $flag
	 * @param string $input
	 * @param int $offset
	 * @param bool $match
	 * @param int $length
	 * 
	 * @covers Choice
	 * @dataProvider choiceData
	 */
	public function testChoice(Choice $parser, string $flag, string $input, int $offset, bool $match, int $length)
	{
		if ($flag) {
			$parser = new \vanderlee\comprehend\directive\Prefer($parser, $flag);
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
			[new Choice('a', 'b'), Context::PREFER_FIRST, '', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_FIRST, 'a', 0, true, 1],
			[new Choice('a', 'b'), Context::PREFER_FIRST, 'b', 0, true, 1],
			[new Choice('a', 'b'), Context::PREFER_FIRST, 'c', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_FIRST, 'za', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_FIRST, 'za', 1, true, 1],
			[new Choice('a', 'ab'), Context::PREFER_FIRST, 'ab', 0, true, 1],
			[new Choice('ab', 'a'), Context::PREFER_FIRST, 'ab', 0, true, 2],
			[new Choice('abc', 'aaa'), Context::PREFER_FIRST, 'ab', 0, false, 2],
			[new Choice('aaa', 'abc'), Context::PREFER_FIRST, 'ab', 0, false, 2],
			[new Choice('a', 'b'), Context::PREFER_LONGEST, '', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_LONGEST, 'a', 0, true, 1],
			[new Choice('a', 'b'), Context::PREFER_LONGEST, 'b', 0, true, 1],
			[new Choice('a', 'b'), Context::PREFER_LONGEST, 'c', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_LONGEST, 'za', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_LONGEST, 'za', 1, true, 1],
			[new Choice('a', 'ab'), Context::PREFER_LONGEST, 'ab', 0, true, 2],
			[new Choice('ab', 'a'), Context::PREFER_LONGEST, 'ab', 0, true, 2],
			[new Choice('abc', 'aaa'), Context::PREFER_LONGEST, 'ab', 0, false, 2],
			[new Choice('aaa', 'abc'), Context::PREFER_LONGEST, 'ab', 0, false, 2],
			[new Choice('a', 'b'), Context::PREFER_SHORTEST, '', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_SHORTEST, 'a', 0, true, 1],
			[new Choice('a', 'b'), Context::PREFER_SHORTEST, 'b', 0, true, 1],
			[new Choice('a', 'b'), Context::PREFER_SHORTEST, 'c', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_SHORTEST, 'za', 0, false, 0],
			[new Choice('a', 'b'), Context::PREFER_SHORTEST, 'za', 1, true, 1],
			[new Choice('a', 'ab'), Context::PREFER_SHORTEST, 'ab', 0, true, 1],
			[new Choice('ab', 'a'), Context::PREFER_SHORTEST, 'ab', 0, true, 1],
			[new Choice('abc', 'aaa'), Context::PREFER_SHORTEST, 'ab', 0, false, 1],
			[new Choice('aaa', 'abc'), Context::PREFER_SHORTEST, 'ab', 0, false, 1],
				//@todo all things being equal, prefer first.
		];
	}

	public function testResultAs()
	{
		$parser = (new Choice(
				(new Text('a'))->resultAs('valueA'), (new Text('b'))->resultAs('valueB')
				))->resultAs('choice');

		$match = $parser->match('a');
		$this->assertTrue($match->match);
		$this->assertTrue($match->hasResult('choice'));
		$this->assertEquals('a', $match->getResult('choice'));
		$this->assertTrue($match->hasResult('valueA'));
		$this->assertEquals('a', $match->getResult('valueA'));
		$this->assertFalse($match->hasResult('valueB'));
		$this->assertEquals(null, $match->getResult('valueB'));

		$match = $parser->match('b');
		$this->assertTrue($match->match);
		$this->assertTrue($match->hasResult('choice'));
		$this->assertEquals('b', $match->getResult('choice'));
		$this->assertFalse($match->hasResult('valueA'));
		$this->assertEquals(null, $match->getResult('valueA'));
		$this->assertTrue($match->hasResult('valueB'));
		$this->assertEquals('b', $match->getResult('valueB'));

		$match = $parser->match('c');
		$this->assertFalse($match->match);
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
