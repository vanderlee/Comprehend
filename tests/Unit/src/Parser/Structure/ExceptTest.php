<?php

namespace Tests\Unit\src\Parser\Structure;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class ExceptTest extends ParserTestCase
{
    public static function exceptData()
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

uses(ExceptTest::class)->group('structure', 'parser');

test('constructor', function () {
    $this->assertInstanceOf(Except::class, new Except(new Text('a'), new Text('aa')));
});

test('except', function (Except $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(ExceptTest::exceptData());
