<?php

namespace Tests\Unit\src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Start;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class StartTest extends ParserTestCase
{
    public static function startData()
    {
        return [[new Start(), 'aa', 0, true, 0], [new Start(), 'aa', 1, false, 0], [new Start(), 'aa', 2, false, 0]];
    }
}

uses(StartTest::class)->group('structure', 'parser');

test('start', function (Start $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(StartTest::startData());
