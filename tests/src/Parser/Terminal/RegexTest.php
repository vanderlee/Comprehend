<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Regex;

abstract class RegexTest extends ParserTestCase
{
    public static function regexData()
    {
        return [
            [new Regex('~[a-f]+~i'), 'abc', 0, true, 3],
            [new Regex('~[a-f]+~i'), 'abcz', 0, true, 3],
            [new Regex('~[a-f]+~i'), 'zabc', 0, false, 0],
            [new Regex('~[a-f]*~i'), 'zabc', 0, false, 0],
            [new Regex('~[a-f]+~i'), 'AbC', 0, true, 3],
            [new Regex('~[a-f]+~i'), 'zabc', 1, true, 3],
            [new Regex('~[a-f]+~'), 'abc', 0, true, 3],
            [new Regex('~[a-f]+~'), 'ABC', 0, false, 0],
            [(new Regex('~[a-f]+~'))->caseInsensitive(), 'ABC', 0, true, 3],
        ];
    }
}

uses(RegexTest::class)->group('terminal', 'parser');

test('construction', function () {
    $this->assertInstanceOf(Regex::class, new Regex('/a/'));
});

test('empty', function () {
    $this->expectExceptionMessage('Empty pattern');
    new Regex('');
});

test('invalid non alphanumeric delimiter', function () {
    $this->expectExceptionMessage('Invalid pattern');
    new Regex('a');
});

test('invalid no ending delimiter', function () {
    $this->expectExceptionMessage('Invalid pattern');
    new Regex('/');
});

test('regex', function (Regex $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $this->assertSame($match, $result->success, (string) $parser);
    $this->assertSame($match ? $length : 0, $result->consumedByteLength, (string) $parser);
})->with(RegexTest::regexData());
