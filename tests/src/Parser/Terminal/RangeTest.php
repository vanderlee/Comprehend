<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Range;

abstract class RangeTest extends ParserTestCase
{
    public static function rangeData()
    {
        return [
            [new Range(null, 'z'), 'foo', 0, true, 1],
            [new Range('a', null), 'foo', 0, true, 1],
            [new Range('a', 'z'), 'foo', 0, true, 1],
            [new Range('A', 'z'), 'foo', 0, true, 1],
            [new Range('A', 'Z'), 'foo', 0, false, 0],
            [new Range('A', 'Z'), 'FOO', 0, true, 1],
            [new Range('a', 'Z'), 'FOO', 0, false, 0],
            [new Range('a', 'z'), '', 0, false, 0],
            [new Range('a', 'z'), 'foo', 1, true, 1],
            [new Range('a', 'z'), 'foo', 3, false, 0],
            [(new Range('a', 'z'))->caseInsensitive(), 'foo', 0, true, 1],
            [(new Range('a', 'z'))->caseInsensitive(), 'FOO', 0, true, 1],
            [(new Range('A', 'Z'))->caseInsensitive(), 'foo', 0, true, 1],
            [(new Range('A', 'Z'))->caseInsensitive(), 'FOO', 0, true, 1],
            [new Range('a', 'm', false), 'foo', 0, false, 0],
            [new Range('a', 'm', false), 'FOO', 0, true, 1],
            [new Range('a', 'm', false), 'zoo', 0, true, 1],
            '[a-m]/i > FOO' => [(new Range('a', 'm', false))->caseInsensitive(), 'FOO', 0, false, 0],
            '[a-m]/i > ZOO' => [(new Range('a', 'm', false))->caseInsensitive(), 'ZOO', 0, true, 1],
        ];
    }
}

uses(RangeTest::class)->group('terminal', 'parser');

test('construction', function () {
    $this->assertInstanceOf(Range::class, new Range('a', 'z'));
});

test('both null', function () {
    $this->expectExceptionMessage('Empty arguments');
    new Range(null, null);
});

test('no first', function () {
    $range = new Range(null, 'd');
    $this->assertResult(true, 1, $range->matchPrefix(new ByteSource('c')));
    $this->assertResult(true, 1, $range->matchPrefix(new ByteSource('d')));
    $this->assertResult(false, 0, $range->matchPrefix(new ByteSource('e')));
});

test('no last', function () {
    $range = new Range('d', null);
    $this->assertResult(false, 0, $range->matchPrefix(new ByteSource('c')));
    $this->assertResult(true, 1, $range->matchPrefix(new ByteSource('d')));
    $this->assertResult(true, 1, $range->matchPrefix(new ByteSource('e')));
});

test('first empty', function () {
    $this->expectExceptionMessage('Empty argument');
    new Range('', '');
});

test('last empty', function () {
    $this->expectExceptionMessage('Empty argument');
    new Range('a', '');
});

test('first too long', function () {
    $this->expectExceptionMessage('Non-character argument');
    new Range('aa', 'z');
});

test('last too long', function () {
    $this->expectExceptionMessage('Non-character argument');
    new Range('a', 'zz');
});

test('range', function (Range $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(RangeTest::rangeData());
