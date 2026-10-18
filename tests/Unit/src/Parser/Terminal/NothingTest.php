<?php

namespace Tests\Unit\src\Parser\Terminal;

use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class NothingTest extends ParserTestCase
{
    public static function nothingData()
    {
        return [
            'Empty, start' => [new Nothing(), '', 0, true, 0],
            'Char, start' => [new Nothing(), 'a', 0, true, 0],
            'Char, end' => [new Nothing(), 'a', 1, true, 0],
        ];
    }
}

uses(NothingTest::class)->group('terminal', 'parser');

test('nothing', function (Nothing $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(NothingTest::nothingData());
