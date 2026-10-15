<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\End;

abstract class EndTest extends ParserTestCase
{
    public static function endData()
    {
        return [[new End(), 'aa', 0, false, 0], [new End(), 'aa', 1, false, 0], [new End(), 'aa', 2, true, 0]];
    }
}

uses(EndTest::class)->group('structure', 'parser');

test('end', function (End $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(EndTest::endData());
