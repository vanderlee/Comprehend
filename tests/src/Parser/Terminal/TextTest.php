<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Text;

abstract class TextTest extends ParserTestCase
{
    public static function textData()
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

uses(TextTest::class);

test('construction', function () {
    $this->assertInstanceOf(Text::class, new Text('foo'));
});

test('empty', function () {
    $this->expectExceptionMessage('Empty argument');
    new Text('');
});

test('text', function (Text $parser, $input, $offset, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input), $offset), (string) $parser);
})->with(TextTest::textData())->group('terminal', 'parser');
