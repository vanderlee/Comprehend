<?php

namespace Tests\Src\Parser\Structure;

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;
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
        $this->assertInstanceOf(Not::class, new Not(new Text('a')));
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
        $result = $parser->matchPrefix(new ByteSource($input), $offset);
        $this->assertSame($match, $result->success, (string) $parser);
        $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
    }

    public function notData()
    {
        return [
            [new Not(new Text('a')), 'a', 0, false, 1],
            [new Not(new Text('aa')), 'aa', 0, false, 2],
            [new Not(new Text('ab')), 'aa', 0, true, 0],
            [new Not(new Text('a')), 'b', 0, true, 0],
            [new Not(new Text('ab')), 'ac', 0, true, 0],
            [new Not(new Text('a')), 'ab', 1, true, 0],
        ];
    }
}
