<?php

namespace Tests\Src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Set;

/**
 * @group terminal
 * @group parser
 */
class SetTest extends ParserTestCase
{
    public function testConstruction()
    {
        $this->assertInstanceOf(Set::class, new Set('abc'));
    }

    public function testEmpty()
    {
        $this->expectExceptionMessage('Empty set');
        new Set('');
    }

    /**
     * @dataProvider setData
     */
    public function testSet(Set $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function setData()
    {
        return [
            [new Set('a'), 'abc', 0, true, 1],
            [new Set('a'), 'abc', 999, false, 0],
            [new Set('b'), 'abc', 0, false, 0],
            [new Set('az'), 'abc', 0, true, 1],
            [new Set('az'), 'b', 0, false, 0],
            [new Set('az'), 'z', 0, true, 1],
            [new Set('abc'), 'a', 0, true, 1],
            [new Set('abc'), 'b', 0, true, 1],
            [new Set('abc'), 'c', 0, true, 1],
            [new Set('abc'), 'za', 0, false, 0],
            [new Set('abc'), 'za', 1, true, 1],
            [(new Set('a'))->caseInsensitive(), 'abc', 0, true, 1],
            [(new Set('a'))->caseInsensitive(), 'ABC', 0, true, 1],
            [(new Set('A'))->caseInsensitive(), 'abc', 0, true, 1],
            [(new Set('A'))->caseInsensitive(), 'ABC', 0, true, 1],
            [new Set('a', false), 'a', 0, false, 0],
            [new Set('a', false), 'b', 0, true, 1],
            [new Set('a', false), 'A', 0, true, 1],
            [(new Set('a', false))->caseInsensitive(), 'A', 0, false, 0],
        ];
    }

}
