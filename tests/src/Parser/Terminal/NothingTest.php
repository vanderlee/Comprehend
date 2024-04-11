<?php

namespace Tests\Src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;

/**
 * @group terminal
 * @group parser
 */
class NothingTest extends ParserTestCase
{
    /**
     * @dataProvider nothingData
     *
     * @param Nothing $parser
     * @param         $input
     * @param         $offset
     * @param         $match
     * @param         $length
     */
    public function testNothing(Nothing $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function nothingData()
    {
        return [
            'Empty, start' => [new Nothing(), '', 0, true, 0],
            'Empty, beyond' => [new Nothing(), '', 1, false, 0],
            'Char, start' => [new Nothing(), 'a', 0, true, 0],
            'Char, end' => [new Nothing(), 'a', 1, true, 0],
            'Char, beyond' => [new Nothing(), 'a', 2, false, 0],
        ];
    }
}
