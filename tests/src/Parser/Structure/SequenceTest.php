<?php

namespace Tests\Src\Parser\Structure;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * @group structure
 * @group parser
 */
class SequenceTest extends ParserTestCase
{

    public function testEmpty()
    {
        $this->expectExceptionMessage("No arguments");
        new Sequence();
    }

    /**
     * @dataProvider sequenceData
     */
    public function testSequence(Sequence $parser, $input, $offset, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input, $offset), (string)$parser);
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
        ];
    }

    public function testSetResult()
    {
        $parser = (new Sequence(
            (new Text('a'))->setResult('valueA'), (new Text('b'))->setResult('valueB')
        ))->setResult('word');

        $match = $parser->match('a');
        $this->assertFalse($match->match);
        $this->assertFalse($match->hasResult('word'));
        $this->assertEquals(null, $match->getResult('word'));
        $this->assertFalse($match->hasResult('valueA'));
        $this->assertEquals(null, $match->getResult('valueA'));
        $this->assertFalse($match->hasResult('valueB'));
        $this->assertEquals(null, $match->getResult('valueB'));

        $match = $parser->match('ab');
        $this->assertTrue($match->match);
        $this->assertTrue($match->hasResult('word'));
        $this->assertEquals('ab', $match->getResult('word'));
        $this->assertTrue($match->hasResult('valueA'));
        $this->assertEquals('a', $match->getResult('valueA'));
        $this->assertTrue($match->hasResult('valueB'));
        $this->assertEquals('b', $match->getResult('valueB'));
    }

    public function testAssignTo()
    {
        $a = $b = $sequence = null;

        $parser = (new Sequence(
            (new Text('a'))->assignTo($a), (new Text('b'))->assignTo($b)
        ))->assignTo($sequence);

        $a     = $b = $sequence = null;
        $match = $parser->match('a');
        $this->assertFalse($match->match);
        $this->assertEquals(null, $sequence);
        $this->assertEquals(null, $a);
        $this->assertEquals(null, $b);

        $a     = $b = $sequence = null;
        $match = $parser->match('ab');
        $this->assertTrue($match->match);
        $this->assertEquals('ab', $sequence);
        $this->assertEquals('a', $a);
        $this->assertEquals('b', $b);
    }

    public function testArrayAccess()
    {
        $seq = new Sequence('a', 'b');

        $this->assertResult(true, 2, $seq->match('ab'));
        $this->assertResult(true, 2, $seq->match('abc'));

        $seq[] = 'c';

        $this->assertResult(false, 2, $seq->match('ab'));
        $this->assertResult(true, 3, $seq->match('abc'));

        unset($seq[0]);

        $this->assertResult(false, 0, $seq->match('abc'));
        $this->assertResult(true, 2, $seq->match('bc'));
    }

    public function testFailedSkip()
    {
        $seq = (new Sequence('a', 'b'))->spacing('-');

        $this->assertResult(false, 1, $seq->match('ab'));
    }

    public function testAddParser()
    {
        $seq = new Sequence('a', 'b');

        $this->assertResult(true, 2, $seq->match('ab'));
        $this->assertResult(true, 2, $seq->match('abc'));

        $seq->add('c');
        $this->assertResult(true, 3, $seq->match('abc'));
    }

}
