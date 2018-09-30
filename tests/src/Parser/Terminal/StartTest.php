<?php

namespace Tests\Src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Start;

/**
 * @group structure
 * @group parser
 */
class StartTest extends ParserTestCase
{

    /**
     * @dataProvider startData
     *
     * @param Start $parser
     * @param $input
     * @param $offset
     * @param $match
     * @param $length
     */
    public function testStart(Start $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function startData()
    {
        return [
            [new Start(), 'aa', 0, true, 0],
            [new Start(), 'aa', 1, false, 0],
            [new Start(), 'aa', 2, false, 0],
        ];
    }

}
