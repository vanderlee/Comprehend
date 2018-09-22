<?php

namespace tests\src\parser\terminal;

use tests\ParserTestCase;
use vanderlee\comprehend\parser\terminal\Start;

/**
 * @group structure
 * @group parser
 */
class StartTest extends ParserTestCase
{

    /**
     * @dataProvider startData
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
