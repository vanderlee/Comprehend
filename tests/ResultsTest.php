<?php

use \vanderlee\comprehend\parser\structure\Sequence;
use \vanderlee\comprehend\parser\terminal\Text;
use \vanderlee\comprehend\match\Match;
use \vanderlee\comprehend\parser\Parser;

/**
 * Test the results
 *
 * @author Martijn
 */
class ResultsTest extends TestCase {

	/**
	 * @covers Match::getResult
	 * @covers Parser::resultAs
	 * @covers Parser::concatResult
	 */
	public function testConcatFlat()
	{
		$parser = new Sequence(
				(new Text('foo'))->resultAs('word'), (new Text('bar')), (new Text('baz'))->concatResult('word')
		);

		$match = $parser->match('foobarbaz');

		$this->assertResult(true, 9, $match);
		$this->assertEquals('foobaz', $match->results['word']);
		$this->assertEquals('foobaz', $match->getResult('word'));
	}

	/**
	 * @covers Match::getResult
	 * @covers Parser::addResult
	 * @covers Parser::concatResult
	 */
	public function testConcatArray()
	{
		$parser = new Sequence(
				(new Text('foo'))->addResult('word'), (new Text('bar')), (new Text('baz'))->concatResult('word')
		);

		$match = $parser->match('foobarbaz');

		$this->assertResult(true, 9, $match);
		$this->assertEquals(['foobaz'], $match->results['word']);
		$this->assertEquals(['foobaz'], $match->getResult('word'));
	}

	/**
	 * @covers Match::getResult
	 * @covers Parser::resultAs
	 * @covers Parser::addResult
	 */
	public function testFlatToArray()
	{
		$parser = new Sequence(
				(new Text('foo'))->resultAs('word'), (new Text('bar')), (new Text('baz'))->addResult('word')
		);

		$match = $parser->match('foobarbaz');

		$this->assertResult(true, 9, $match);
		$this->assertEquals(['foo', 'baz'], $match->results['word']);
		$this->assertEquals(['foo', 'baz'], $match->getResult('word'));
	}

	/**
	 * @covers Match::getResult
	 * @covers Parser::resultAs
	 * @covers Parser::concatResult
	 */
	public function testDefaultResult()
	{
		$parser = new Sequence(
				(new Text('foo'))->resultAs(), (new Text('bar')), (new Text('baz'))->concatResult()
		);

		$match = $parser->match('foobarbaz');

		$this->assertResult(true, 9, $match);
		$this->assertEquals('foobaz', $match->result);
		$this->assertEquals('foobaz', $match->getResult());
		$this->assertEmpty($match->results);
	}

}
