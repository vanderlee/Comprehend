<?php

namespace Tests\Src\Parser\Terminal;

use Vanderlee\Comprehend\Source\ByteSource;
use Exception;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Parser\Terminal\Integer;

abstract class IntegerTest extends ParserTestCase
{
    /**
     * @return array
     * @throws Exception
     *
     */
    public static function integerData()
    {
        return [
            [new Integer(), '0', 0, true, 1],
            [new Integer(), '1', 0, true, 1],
            [new Integer(), '99999', 0, true, 5],
            [new Integer(), '00', 0, true, 1],
            [new Integer(), '0a', 0, true, 1],
            [new Integer(), '01', 0, true, 1],
            [new Integer(), 'a', 0, false, 0],
            [new Integer(), '-1', 0, false, 0],
            [new Integer(), '-1', 1, true, 1],
            [new Integer(), '-a1', 1, false, 0],
            [new Integer(), '-1a', 1, true, 1],
            [new Integer(null, 0), '0', 0, true, 1],
            [new Integer(null, 0), '1', 0, false, 0],
            [new Integer(null, 0), '-1', 0, true, 2],
            [new Integer(null, 0), '-99999', 0, true, 6],
            [new Integer(100, 100), '99', 0, false, 0],
            [new Integer(100, 100), '100', 0, true, 3],
            [new Integer(100, 100), '101', 0, false, 0],
            [new Integer(-100, -100), '-99', 0, false, 0],
            [new Integer(-100, -100), '-100', 0, true, 4],
            [new Integer(-100, -100), '-101', 0, false, 0],
            [new Integer(100, 200), '150', 0, true, 3],
            [new Integer(100, 200), '-150', 0, false, 0],
            [new Integer(-200, -100), '150', 0, false, 0],
            [new Integer(-200, -100), '-150', 0, true, 4],
            [new Integer(null, null), '0', 0, true, 1],
            [new Integer(null, null), '99999', 0, true, 5],
            [new Integer(null, null), '-99999', 0, true, 6],
            [new Integer(0, 254, 16), 'fe', 0, true, 2],
            [new Integer(0, 254, 16), 'ff', 0, true, 1],
            [new Integer(16, 254, 16), 'ff', 0, false, 0],
            [new Integer(0, 7, 2), '111', 0, true, 3],
            [new Integer(0, 6, 2), '111', 0, true, 2],
            [new Integer(4, 6, 2), '111', 0, false, 0],
            [new Integer(0, 254, 16), 'FE', 0, false, 0],
            [(new Integer(0, 254, 16))->caseInsensitive(), 'FE', 0, true, 2],
            [(new Integer(0, 254, 16))->caseInsensitive(), 'FF', 0, true, 1],
            [(new Integer(16, 254, 16))->caseInsensitive(), 'FF', 0, false, 0],
        ];
    }
}

uses(IntegerTest::class)->group('terminal', 'parser');

test('constructor', function () {
    $this->assertInstanceOf(Integer::class, new Integer());
});

test('constructor bad minimum', function () {
    $this->expectException(\TypeError::class);
    new Integer('a');
});

test('constructor bad maximum', function () {
    $this->expectException(\TypeError::class);
    new Integer(0, 'a');
});

test('constructor bad order', function () {
    $this->expectExceptionMessage('Maximum must be greater than minimum');
    new Integer(1, -1);
});

test('constructor invalid base too low', function () {
    $this->expectExceptionMessage('Unsupported base');
    new Integer(0, null, 1);
});

test('constructor invalid base too high', function () {
    $this->expectExceptionMessage('Unsupported base');
    new Integer(0, null, 10 + 26 + 1);
});

test('integer', function (Integer $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $message = $input . ' = ' . (string) $parser;
    $this->assertResult($match, $length, $result, $message);
})->with(IntegerTest::integerData());
