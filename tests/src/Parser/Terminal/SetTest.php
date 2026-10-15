<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Exception;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Set;

abstract class SetTest extends ParserTestCase
{
    /**
     * @return array
     * @throws Exception
     *
     */
    public static function setCases()
    {
        return [
            [new Set('a'), 'abc', 0, true, 1],
            [new Set('b'), 'abc', 0, false, 0],
            [new Set('az'), 'abc', 0, true, 1],
            [new Set('az'), 'b', 0, false, 0],
            [new Set('az'), 'z', 0, true, 1],
            [new Set('abc'), 'a', 0, true, 1],
            [new Set('abc'), 'b', 0, true, 1],
            [new Set('abc'), 'c', 0, true, 1],
            [new Set('abc'), 'za', 0, false, 0],
            [new Set('abc'), 'za', 1, true, 1],
            [(new Set('a'))->caseInsensitive(), 'abc', 0, true, 1],
            [(new Set('a'))->caseInsensitive(), 'ABC', 0, true, 1],
            [(new Set('A'))->caseInsensitive(), 'abc', 0, true, 1],
            [(new Set('A'))->caseInsensitive(), 'ABC', 0, true, 1],
            [new Set('a', false), 'a', 0, false, 0],
            [new Set('a', false), 'b', 0, true, 1],
            [new Set('a', false), 'A', 0, true, 1],
            [(new Set('a', false))->caseInsensitive(), 'A', 0, false, 0],
        ];
    }
}

uses(SetTest::class)->group('terminal', 'parser');

test('construction', function () {
    $this->assertInstanceOf(Set::class, new Set('abc'));
});

test('empty', function () {
    $this->expectExceptionMessage('Empty set');
    new Set('');
});

test('set', function (Set $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(SetTest::setCases());
