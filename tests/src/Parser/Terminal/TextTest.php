<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Parser\Terminal\Text;

class TextTest extends \Tests\ParserTestCase
{
    public function testConstruction()
    {
        $this->assertInstanceOf(Text::class, new Text('foo'));
    }

    public function testEmpty()
    {
        $this->expectExceptionMessage('Empty argument');
        new Text('');
    }

    /**
     * @group        terminal
     * @group        parser
     * @dataProvider textData
     *
     * @param Text   $parser
     * @param string $input
     * @param int    $offset
     * @param bool   $match
     * @param int    $length
     */
    public function testText(Text $parser, $input, $offset, $match, $length)
    {
        $this->assertResult($match, $length, $parser->match($input, $offset), (string) $parser);
    }

    public function textData()
    {
        return [
            [new Text('foo'), 'foo', 0, true, 3],
            [new Text('foo'), 'foobar', 0, true, 3],
            [new Text('FOO'), 'foo', 0, false, 0],
            [new Text('foo'), 'FOO', 0, false, 0],
            [new Text('foo'), 'faa', 0, false, 1],
            [new Text('bar'), 'foobar', 0, false, 0],
            [new Text('bar'), 'foobar', 3, true, 3],
            [new Text('bar'), 'foobaz', 3, false, 2],
            [(new Text('foo'))->caseInsensitive(), 'foo', 0, true, 3],
            [(new Text('foo'))->caseInsensitive(), 'FOO', 0, true, 3],
            [(new Text('FOO'))->caseInsensitive(), 'foo', 0, true, 3],
            [(new Text('FOO'))->caseInsensitive(), 'FOO', 0, true, 3],
        ];
    }
}
