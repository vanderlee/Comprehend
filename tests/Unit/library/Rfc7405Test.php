<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

use Tests\Fixtures\Rfc7405TestCase;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\ByteSource;

uses(Rfc7405TestCase::class)->group('library', 'rfc');

test('rfc7405', function (Parser $parser, $input, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input)), (string) $parser);
})->with(Rfc7405TestCase::rfc7405Data());

test('construct', function () {
    $this->assertNotNull(static::getRfc());
});

test('rfc2234', function (Parser $parser, $input, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input)), (string) $parser);
})->with(Rfc7405TestCase::rfc2234Data());
