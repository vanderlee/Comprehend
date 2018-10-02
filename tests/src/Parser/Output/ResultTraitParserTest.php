<?php

namespace Tests\Src\Parser\Output;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;

/**
 * Test the results.
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

    public function testSetResultValue()
    {
        $parser = (new Text('foo'))->setResult('var', 'found it');
        $match = $parser->match('foobar');

        $this->assertResult(true, 3, $match);
        $this->assertEquals('found it', $match->results['var']);
        $this->assertEquals('found it', $match->getResult('var'));
    }

    public function testSetResultValueCallback()
    {
        $parser = (new Text('foo'))->setResult('var', function ($text) {
            return strrev($text);
        });
        $match = $parser->match('foobarbaz');

        $this->assertResult(true, 3, $match);
        $this->assertEquals('oof', $match->results['var']);
        $this->assertEquals('oof', $match->getResult('var'));
    }

    public function testConcatResult()
    {
        $parser = new Sequence(
            (new Text('foo'))->concatResult('var'),                     // initial
            (new Text('bar'))->concatResult('var', 'BAR'),              // concat w/ value
            (new Text('baz'))->concatResult('var', function ($text) {   // concat w/ callback
                return strrev($text);
            })
        );
        $match = $parser->match('foobarbazbob');

        $this->assertResult(true, 9, $match);
        $this->assertEquals('fooBARzab', $match->results['var']);
        $this->assertEquals('fooBARzab', $match->getResult('var'));
    }

    public function testPushResult()
    {
        $parser = new Sequence(
            (new Text('foo'))->pushResult('var'),                     // initial
            (new Text('bar'))->pushResult('var', 'BAR'),              // push w/ value
            (new Text('baz'))->pushResult('var', function ($text) {   // push w/ callback
                return strrev($text);
            })
        );
        $match = $parser->match('foobarbazbob');

        $this->assertResult(true, 9, $match);
        $this->assertEquals(['foo', 'BAR', 'zab'], $match->results['var']);
        $this->assertEquals(['foo', 'BAR', 'zab'], $match->getResult('var'));
    }
}
