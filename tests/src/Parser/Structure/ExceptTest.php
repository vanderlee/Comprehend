<?php

namespace Tests\Src\Parser\Structure;

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
        $this->assertInstanceOf(Except::class, new Except('a', 'aa'));
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
        $result = $parser->match($input, $offset);

        $this->assertSame($match, $result->match, (string) $parser);
        $this->assertSame($length, $result->length, (string) $parser);
    }

    public function exceptData()
    {
        return [
            [new Except('a', 'aa'), '', 0, false, 0],
            [new Except('a', 'aa'), 'aa', 0, false, 1],
            [new Except('aa', 'a'), 'aa', 0, false, 1],
            [new Except('a', 'b'), 'a', 0, true, 1],
            [new Except('a', 'b'), 'ab', 0, true, 1],
            [new Except('a', 'b'), 'ab', 1, false, 0],
            [new Except('a', 'b'), 'ba', 1, true, 1],
            [new Except('a', 'b'), 'b', 0, false, 0],
        ];
    }
}
