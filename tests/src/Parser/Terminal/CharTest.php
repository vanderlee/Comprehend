<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Char;

abstract class CharTest extends ParserTestCase
{
    public static function charData()
    {
        return [
            [new Char('a'), 'a', 0, true, 1],
            [new Char('a'), 'aa', 0, true, 1],
            [new Char('a'), 'A', 0, false, 0],
            [new Char('A'), 'a', 0, false, 0],
            [new Char('a'), 'b', 0, false, 0],
            [new Char('a'), '', 0, false, 0],
            [new Char('a'), 'ba', 0, false, 0],
            [new Char('a'), 'ba', 1, true, 1],
            [new Char(chr(ord('a'))), 'a', 0, true, 1],
            [(new Char('a'))->caseInsensitive(), 'a', 0, true, 1],
            [(new Char('A'))->caseInsensitive(), 'A', 0, true, 1],
            [(new Char('a'))->caseInsensitive(), 'A', 0, true, 1],
            [(new Char('A'))->caseInsensitive(), 'a', 0, true, 1],
            [(new Char('a'))->setCaseSensitivity(false), 'a', 0, true, 1],
            [(new Char('A'))->setCaseSensitivity(false), 'A', 0, true, 1],
            [(new Char('a'))->setCaseSensitivity(false), 'A', 0, true, 1],
            [(new Char('A'))->setCaseSensitivity(false), 'a', 0, true, 1],
            [new Char('a', false), 'a', 0, false, 0],
            [new Char('a', false), 'A', 0, true, 1],
            [new Char('a', false), 'b', 0, true, 1],
            [(new Char('a', false))->caseInsensitive(), 'A', 0, false, 0],
            [(new Char('a', false))->caseInsensitive(), 'b', 0, true, 1],
        ];
    }
}

uses(CharTest::class)->group('terminal', 'parser');

test('construction', function () {
    $this->assertInstanceOf(Char::class, new Char('a'));
});

test('empty', function () {
    $this->expectExceptionMessage('Empty argument');
    new Char('');
});

test('too long', function () {
    $this->expectExceptionMessage('Non-character argument');
    new Char('aa');
});

test('char', function (Char $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(CharTest::charData());
