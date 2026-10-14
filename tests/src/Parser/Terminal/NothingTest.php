<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
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
        $result = $parser->matchPrefix(new ByteSource($input), $offset);
        $this->assertSame($match, $result->success, (string) $parser);
        $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
    }
    public function nothingData()
    {
        return [
            'Empty, start' => [new Nothing(), '', 0, true, 0],
            'Char, start' => [new Nothing(), 'a', 0, true, 0],
            'Char, end' => [new Nothing(), 'a', 1, true, 0],
        ];
    }
}
