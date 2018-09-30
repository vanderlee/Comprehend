<?php

namespace Tests\Src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Range;

/**
 * @group terminal
 * @group parser
 */
class RangeTest extends ParserTestCase
{
    public function testConstruction()
    {
        $this->assertInstanceOf(Range::class, new Range('a', 'z'));
    }

    public function testBothNull()
    {
        $this->expectExceptionMessage('Empty arguments');
        new Range(null, null);
    }

    public function testNoFirst()
    {
        $range = new Range(null, 'd');
        $this->assertResult(true, 1, $range->match('c'));
        $this->assertResult(true, 1, $range->match('d'));
        $this->assertResult(false, 0, $range->match('e'));
    }

    public function testNoLast()
    {
        $range = new Range('d', null);
        $this->assertResult(false, 0, $range->match('c'));
        $this->assertResult(true, 1, $range->match('d'));
        $this->assertResult(true, 1, $range->match('e'));
    }

    public function testFirstEmpty()
    {
        $this->expectExceptionMessage('Empty argument');
        new Range('', '');
    }

    public function testLastEmpty()
    {
        $this->expectExceptionMessage('Empty argument');
        new Range('a', '');
    }

    public function testFirstTooLong()
    {
        $this->expectExceptionMessage('Non-character argument');
        new Range('aa', 'z');
    }

    public function testLastTooLong()
    {
        $this->expectExceptionMessage('Non-character argument');
        new Range('a', 'zz');
    }

    /**
     * @dataProvider rangeData
     *
     * @param Range $parser
     * @param $input
     * @param $offset
     * @param $match
     * @param $length
     */
    public function testRange(Range $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function rangeData()
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
