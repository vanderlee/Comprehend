<?php

namespace Tests\Src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Any;

/**
 * @group terminal
 * @group parser
 */
class AnyTest extends ParserTestCase
{
    /**
     * @dataProvider anyData
     *
     * @param Any    $parser
     * @param string $input
     * @param int    $offset
     * @param bool   $match
     * @param int    $length
     */
    public function testAny(Any $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string) $parser);
        $this->assertSame($length, $result->length, (string) $parser);
    }

    public function anyData()
    {
        return [
            [new Any(), 'a', 0, true, 1],
            [new Any(), '', 0, false, 0],
            [new Any(), ' ', 0, true, 1],
            [new Any(), 'a', 1, false, 0],
            [new Any(), 'aa', 1, true, 1],
        ];
    }
}
