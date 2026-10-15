<?php

namespace Tests\Src\Parser\Structure;

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Except;

/**
 * @group structure
 * @group parser
 */
class ExceptTest extends ParserTestCase
{
    public function testConstructor()
    {
        $this->assertInstanceOf(Except::class, new Except(new Text('a'), new Text('aa')));
    }
    /**
     * @dataProvider exceptData
     *
     * @param Except $parser
     * @param        $input
     * @param        $offset
     * @param        $match
     * @param        $length
     */
    public function testExcept(Except $parser, $input, $offset, $match, $length)
    {
        $result = $parser->matchPrefix(new ByteSource($input), $offset);
        $this->assertSame($match, $result->success, (string) $parser);
        $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
    }
    public function exceptData()
    {
        return [
            [new Except(new Text('a'), new Text('aa')), '', 0, false, 0],
            [new Except(new Text('a'), new Text('aa')), 'aa', 0, false, 1],
            [new Except(new Text('aa'), new Text('a')), 'aa', 0, false, 1],
            [new Except(new Text('a'), new Text('b')), 'a', 0, true, 1],
            [new Except(new Text('a'), new Text('b')), 'ab', 0, true, 1],
            [new Except(new Text('a'), new Text('b')), 'ab', 1, false, 0],
            [new Except(new Text('a'), new Text('b')), 'ba', 1, true, 1],
            [new Except(new Text('a'), new Text('b')), 'b', 0, false, 0],
        ];
    }
}
