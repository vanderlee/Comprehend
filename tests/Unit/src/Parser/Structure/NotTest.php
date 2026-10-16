<?php

namespace Tests\Unit\src\Parser\Structure;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class NotTest extends ParserTestCase
{
    public static function notData()
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

uses(NotTest::class)->group('structure', 'parser');

test('constructor', function () {
    $this->assertInstanceOf(Not::class, new Not(new Text('a')));
});

test('not', function (Not $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(NotTest::notData());
