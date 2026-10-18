<?php

namespace Tests\Unit\extensions\Numbers\Parser;

use Exception;
use Tests\ParserTestCase;
use Vanderlee\Comprehend\Extensions\Numbers\Parser\IntegerNumber;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class IntegerNumberTest extends ParserTestCase
{
    /**
     * @return array
     * @throws Exception
     *
     */
    public static function integerData()
    {
        return [
            [new IntegerNumber(), '0', 0, true, 1],
            [new IntegerNumber(), '1', 0, true, 1],
            [new IntegerNumber(), '99999', 0, true, 5],
            [new IntegerNumber(), '00', 0, true, 1],
            [new IntegerNumber(), '0a', 0, true, 1],
            [new IntegerNumber(), '01', 0, true, 1],
            [new IntegerNumber(), 'a', 0, false, 0],
            [new IntegerNumber(), '-1', 0, false, 0],
            [new IntegerNumber(), '-1', 1, true, 1],
            [new IntegerNumber(), '-a1', 1, false, 0],
            [new IntegerNumber(), '-1a', 1, true, 1],
            [new IntegerNumber(null, 0), '0', 0, true, 1],
            [new IntegerNumber(null, 0), '1', 0, false, 0],
            [new IntegerNumber(null, 0), '-1', 0, true, 2],
            [new IntegerNumber(null, 0), '-99999', 0, true, 6],
            [new IntegerNumber(100, 100), '99', 0, false, 0],
            [new IntegerNumber(100, 100), '100', 0, true, 3],
            [new IntegerNumber(100, 100), '101', 0, false, 0],
            [new IntegerNumber(-100, -100), '-99', 0, false, 0],
            [new IntegerNumber(-100, -100), '-100', 0, true, 4],
            [new IntegerNumber(-100, -100), '-101', 0, false, 0],
            [new IntegerNumber(100, 200), '150', 0, true, 3],
            [new IntegerNumber(100, 200), '-150', 0, false, 0],
            [new IntegerNumber(-200, -100), '150', 0, false, 0],
            [new IntegerNumber(-200, -100), '-150', 0, true, 4],
            [new IntegerNumber(null, null), '0', 0, true, 1],
            [new IntegerNumber(null, null), '99999', 0, true, 5],
            [new IntegerNumber(null, null), '-99999', 0, true, 6],
            [new IntegerNumber(0, 254, 16), 'fe', 0, true, 2],
            [new IntegerNumber(0, 254, 16), 'ff', 0, true, 1],
            [new IntegerNumber(16, 254, 16), 'ff', 0, false, 0],
            [new IntegerNumber(0, 7, 2), '111', 0, true, 3],
            [new IntegerNumber(0, 6, 2), '111', 0, true, 2],
            [new IntegerNumber(4, 6, 2), '111', 0, false, 0],
            [new IntegerNumber(0, 254, 16), 'FE', 0, false, 0],
            [(new IntegerNumber(0, 254, 16))->caseInsensitive(), 'FE', 0, true, 2],
            [(new IntegerNumber(0, 254, 16))->caseInsensitive(), 'FF', 0, true, 1],
            [(new IntegerNumber(16, 254, 16))->caseInsensitive(), 'FF', 0, false, 0],
        ];
    }
}

uses(IntegerNumberTest::class)->group('terminal', 'parser');

test('constructor', function () {
    $this->assertInstanceOf(IntegerNumber::class, new IntegerNumber());
});

test('constructor bad minimum', function () {
    $this->expectException(\TypeError::class);
    new IntegerNumber('a');
});

test('constructor bad maximum', function () {
    $this->expectException(\TypeError::class);
    new IntegerNumber(0, 'a');
});

test('constructor bad order', function () {
    $this->expectExceptionMessage('Maximum must be greater than minimum');
    new IntegerNumber(1, -1);
});

test('constructor invalid base too low', function () {
    $this->expectExceptionMessage('Unsupported base');
    new IntegerNumber(0, null, 1);
});

test('constructor invalid base too high', function () {
    $this->expectExceptionMessage('Unsupported base');
    new IntegerNumber(0, null, 10 + 26 + 1);
});

test('integer', function (IntegerNumber $parser, $input, $offset, $match, $length) {
    $result = $parser->matchPrefix(new ByteSource($input), $offset);
    $message = $input . ' = ' . (string) $parser;
    $this->assertResult($match, $length, $result, $message);
})->with(IntegerNumberTest::integerData());
