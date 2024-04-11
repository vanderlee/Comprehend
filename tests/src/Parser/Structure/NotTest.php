<?php

namespace Tests\Src\Parser\Structure;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Not;

/**
 * @group structure
 * @group parser
 */
class NotTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(Not::class, new Not('a'));
    }

    /**
     * @dataProvider notData
     *
     * @param Not $parser
     * @param     $input
     * @param     $offset
     * @param     $match
     * @param     $length
     */
    public function testNot(Not $parser, $input, $offset, $match, $length)
    {
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string)$parser);
        $this->assertSame($length, $result->length, (string)$parser);
    }

    public function notData()
    {
        return [
            [new Not('a'), 'a', 0, false, 1],
            [new Not('aa'), 'aa', 0, false, 2],
            [new Not('ab'), 'aa', 0, true, 0],
            [new Not('a'), 'b', 0, true, 0],
            [new Not('ab'), 'ac', 0, true, 0],
            [new Not('a'), 'ab', 1, true, 0],
        ];
    }
}
