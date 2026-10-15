<?php

namespace Tests\Src\Parser\Structure;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\Text;

abstract class SequenceTest extends ParserTestCase
{
    public static function sequenceData()
    {
        return [
            [new Sequence(new Text('a')), 'a', 0, true, 1],
            [new Sequence(new Text('a')), 'aa', 0, true, 1],
            [new Sequence(new Text('a')), 'b', 0, false, 0],
            [new Sequence(new Text('a')), 'B', 0, false, 0],
            [new Sequence(new Text('abc')), 'abc', 0, true, 3],
            [new Sequence(new Text('a'), new Text('b')), 'ab', 0, true, 2],
            [new Sequence(new Text('b'), new Text('a')), 'ab', 0, false, 0],
            [new Sequence(new Text('a'), new Text('a')), 'ab', 0, false, 1],
            [new Sequence(new Text('a')), '', 0, false, 0],
            [new Sequence(new Text('abc')), 'abc', 0, true, 3],
            [new Sequence(new Char('a')), 'abc', 0, true, 1],
            [new Sequence(new Char('a'), new Text('bc')), 'abc', 0, true, 3],
        ];
    }
}

uses(SequenceTest::class)->group('structure', 'parser');

test('empty', function () {
    $this->expectExceptionMessage('No arguments');
    new Sequence();
});

test('sequence', function (Sequence $parser, $input, $offset, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
})->with(SequenceTest::sequenceData());

test('set result', function () {
    $parser = (new Sequence(
        (new Text('a'))->setResult('valueA'),
        (new Text('b'))->setResult('valueB'),
    ))->setResult('word');
    $match = $parser->matchPrefix(new ByteSource('a'));
    $this->assertFalse($match->success);
    $this->assertFalse(array_key_exists('word', $match->results()));
    $this->assertEquals(null, $match->result('word'));
    $this->assertFalse(array_key_exists('valueA', $match->results()));
    $this->assertEquals(null, $match->result('valueA'));
    $this->assertFalse(array_key_exists('valueB', $match->results()));
    $this->assertEquals(null, $match->result('valueB'));
    $match = $parser->matchPrefix(new ByteSource('ab'));
    $this->assertTrue($match->success);
    $this->assertTrue(array_key_exists('word', $match->results()));
    $this->assertEquals('ab', $match->result('word'));
    $this->assertTrue(array_key_exists('valueA', $match->results()));
    $this->assertEquals('a', $match->result('valueA'));
    $this->assertTrue(array_key_exists('valueB', $match->results()));
    $this->assertEquals('b', $match->result('valueB'));
});

test('assign to', function () {
    $a = $b = $sequence = null;
    $parser = (new Sequence((new Text('a'))->assignTo($a), (new Text('b'))->assignTo($b)))->assignTo($sequence);
    $a = $b = $sequence = null;
    $match = $parser->matchPrefix(new ByteSource('a'));
    $match->commit();
    $this->assertFalse($match->success);
    $this->assertEquals(null, $sequence);
    $this->assertEquals(null, $a);
    $this->assertEquals(null, $b);
    $a = $b = $sequence = null;
    $match = $parser->matchPrefix(new ByteSource('ab'));
    $match->commit();
    $this->assertTrue($match->success);
    $this->assertEquals('ab', $sequence);
    $this->assertEquals('a', $a);
    $this->assertEquals('b', $b);
});

test('array access', function () {
    $seq = new Sequence(new Text('a'), new Text('b'));
    $this->assertResult(true, 2, $seq->matchPrefix(new ByteSource('ab')));
    $this->assertResult(true, 2, $seq->matchPrefix(new ByteSource('abc')));
    $seq[] = new Text('c');
    $this->assertResult(false, 2, $seq->matchPrefix(new ByteSource('ab')));
    $this->assertResult(true, 3, $seq->matchPrefix(new ByteSource('abc')));
    unset($seq[0]);
    $this->assertResult(false, 0, $seq->matchPrefix(new ByteSource('abc')));
    $this->assertResult(true, 2, $seq->matchPrefix(new ByteSource('bc')));
});

test('failed skip', function () {
    $seq = (new Sequence(new Text('a'), new Text('b')))->spacing(new Text('-'));
    $this->assertResult(false, 1, $seq->matchPrefix(new ByteSource('ab')));
});

test('add parser', function () {
    $seq = new Sequence(new Text('a'), new Text('b'));
    $this->assertResult(true, 2, $seq->matchPrefix(new ByteSource('ab')));
    $this->assertResult(true, 2, $seq->matchPrefix(new ByteSource('abc')));
    $seq->add(new Text('c'));
    $this->assertResult(true, 3, $seq->matchPrefix(new ByteSource('abc')));
});
