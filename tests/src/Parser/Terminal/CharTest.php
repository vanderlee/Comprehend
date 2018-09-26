<?php

namespace Tests\Src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Char;

/**
 * @group terminal
 * @group parser
 */
class CharTest extends ParserTestCase
{
    public function testConstruction()
    {
        $this->assertInstanceOf(Char::class, new Char('a'));
    }

    public function testEmpty()
    {
        $this->expectExceptionMessage("Empty argument");
        new Char('');
    }

    public function testTooLong()
    {
        $this->expectExceptionMessage("Non-character argument");
        new Char('aa');
    }

    /**
     * @dataProvider charData
     */
    public function testChar(Char $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function charData()
    {
        return [
            [new Char('a'), 'a', 0, true, 1],
            [new Char('a'), 'aa', 0, true, 1],
            [new Char('a'), 'A', 0, false, 0],
            [new Char('A'), 'a', 0, false, 0],
            [new Char('a'), 'b', 0, false, 0],
            [new Char('a'), '', 0, false, 0],
            [new Char('a'), 'ba', 0, false, 0],
            [new Char('a'), 'ba', 1, true, 1],
            [new Char(ord('a')), 'a', 0, true, 1],
            [(new Char('a'))->caseInsensitive(), 'a', 0, true, 1],
            [(new Char('A'))->caseInsensitive(), 'A', 0, true, 1],
            [(new Char('a'))->caseInsensitive(), 'A', 0, true, 1],
            [(new Char('A'))->caseInsensitive(), 'a', 0, true, 1],
            [(new Char('a'))->setCaseSensitivity(false), 'a', 0, true, 1],
            [(new Char('A'))->setCaseSensitivity(false), 'A', 0, true, 1],
            [(new Char('a'))->setCaseSensitivity(false), 'A', 0, true, 1],
            [(new Char('A'))->setCaseSensitivity(false), 'a', 0, true, 1],
            [new Char('a', false), 'a', 0, false, 0],
            [new Char('a', false), 'A', 0, true, 1],
            [new Char('a', false), 'b', 0, true, 1],
            [(new Char('a', false))->caseInsensitive(), 'A', 0, false, 0],
            [(new Char('a', false))->caseInsensitive(), 'b', 0, true, 1],
        ];
    }

}
