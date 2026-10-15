<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Any;

abstract class AnyTest extends ParserTestCase
{
    public static function anyData()
    {
        return [
            [new Any(), 'a', 0, true, 1],
            [new Any(), '', 0, false, 0],
            [new Any(), ' ', 0, true, 1],
            [new Any(), 'a', 1, false, 0],
            [new Any(), 'aa', 1, true, 1],
        ];
    }
}

uses(AnyTest::class)->group('terminal', 'parser');

test('any', function (Any $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(AnyTest::anyData());
