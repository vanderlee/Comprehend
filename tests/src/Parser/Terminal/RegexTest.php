<?php

namespace Tests\Src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Regex;

/**
 * @group terminal
 * @group parser
 */
class RegexTest extends ParserTestCase
{

    public function testConstruction()
    {
        $this->assertInstanceOf(Regex::class, new Regex('/a/'));
    }

    public function testEmpty()
    {
        $this->expectExceptionMessage('Empty pattern');
        new Regex('');
    }

    public function testInvalidNonAlphanumericDelimiter()
    {
        $this->expectExceptionMessage('Invalid pattern');
        new Regex('a');
    }

    public function testInvalidNoEndingDelimiter()
    {
        $this->expectExceptionMessage('Invalid pattern');
        new Regex('/');
    }

    /**
     * @dataProvider regexData
     *
     * @param Regex $parser
     * @param $input
     * @param $offset
     * @param $match
     * @param $length
     */
    public function testRegex(Regex $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function regexData()
    {
        return [
            [new Regex('~[a-f]+~i'), 'abc', 0, true, 3],
            [new Regex('~[a-f]+~i'), 'abcz', 0, true, 3],
            [new Regex('~[a-f]+~i'), 'zabc', 0, false, 0],
            [new Regex('~[a-f]*~i'), 'zabc', 0, false, 0],
            [new Regex('~[a-f]+~i'), 'AbC', 0, true, 3],
            [new Regex('~[a-f]+~i'), 'zabc', 1, true, 3],
            [new Regex('~[a-f]+~'), 'abc', 0, true, 3],
            [new Regex('~[a-f]+~'), 'ABC', 0, false, 0],
            [(new Regex('~[a-f]+~'))->caseInsensitive(), 'ABC', 0, true, 3],
        ];
    }

}
