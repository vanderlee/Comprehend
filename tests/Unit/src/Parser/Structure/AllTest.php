<?php

namespace Tests\Unit\src\Parser\Structure;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class AllTest extends ParserTestCase
{
    public static function allData()
    {
        return [
            [new All(new Text('a'), new Text('b')), 'a', 0, false, 0],
            [new All(new Text('a'), new Text('b')), 'b', 0, false, 0],
            [new All(new Text('a'), new Text('a')), 'a', 0, true, 1],
            [new All(new Text('aa'), new Text('a')), 'aa', 0, true, 1],
            [new All(new Text('a'), new Text('aa')), 'aa', 0, true, 1],
            [new All(new Text('aa'), new Text('ab')), 'aa', 0, false, 1],
            [new All(new Text('aa'), new Sequence(new Text('a'), new Text('a'))), 'aa', 0, true, 2],
            [new All(new Text('aa'), new Repeat(new Text('a'), 0, 1)), 'aa', 0, true, 1],
            [new All(new Text('aa'), new Repeat(new Text('a'), 0, 2)), 'aa', 0, true, 2],
            [new All(new Text('aa'), new Repeat(new Text('a'), 1, 1)), 'aa', 0, true, 1],
            [new All(new Text('aa'), new Repeat(new Text('a'), 1, 2)), 'aa', 0, true, 2],
            [new All(new Text('aa'), new Repeat(new Text('a'), 2, 2)), 'aa', 0, true, 2],
            [new All(new Text('aa'), new Repeat(new Text('a'), 2, 3)), 'aaa', 0, true, 2],
            [new All(new Text('aa'), new Repeat(new Text('a'), 3, 3)), 'aaa', 0, true, 2],
        ];
    }
}

uses(AllTest::class)->group('structure', 'parser');

test('constructor', function () {
    $this->assertInstanceOf(All::class, new All(new Text('a'), new Text('aa')));
});

test('less than two arguments', function () {
    $this->expectExceptionMessage('Less than 2 arguments provided');
    new All(new Text('a'));
});

test('all', function (All $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(AllTest::allData());
