<?php

namespace tests\src\parser;

use tests\ParserTestCase;
use vanderlee\comprehend\parser\structure\Sequence;
use vanderlee\comprehend\parser\terminal\Text;

/**
 * Test the results
 *
 * @author Martijn
 */
class ResultTraitParserTest extends ParserTestCase
{

    public function testConcatFlat()
    {
        $parser = new Sequence(
            (new Text('foo'))->setResult('word'), (new Text('bar')), (new Text('baz'))->concatResult('word')
        );

        $match = $parser->match('foobarbaz');

        $this->assertResult(true, 9, $match);
        $this->assertEquals('foobaz', $match->results['word']);
        $this->assertEquals('foobaz', $match->getResult('word'));
    }

    public function testConcatArray()
    {
        $parser = new Sequence(
            (new Text('foo'))->pushResult('word'), (new Text('bar')), (new Text('baz'))->concatResult('word')
        );

        $match = $parser->match('foobarbaz');

        $this->assertResult(true, 9, $match);
        $this->assertEquals(['foobaz'], $match->results['word']);
        $this->assertEquals(['foobaz'], $match->getResult('word'));
    }

    public function testFlatToArray()
    {
        $parser = new Sequence(
            (new Text('foo'))->setResult('word'), (new Text('bar')), (new Text('baz'))->pushResult('word')
        );

        $match = $parser->match('foobarbaz');

        $this->assertResult(true, 9, $match);
        $this->assertEquals(['foo', 'baz'], $match->results['word']);
        $this->assertEquals(['foo', 'baz'], $match->getResult('word'));
    }

    public function testDefaultResult()
    {
        $parser = new Sequence(
            (new Text('foo'))->setResult(), (new Text('bar')), (new Text('baz'))->concatResult()
        );

        $match = $parser->match('foobarbaz');

        $this->assertResult(true, 9, $match);
        $this->assertEquals('foobaz', $match->result);
        $this->assertEquals('foobaz', $match->getResult());
        $this->assertEmpty($match->results);
    }

}
