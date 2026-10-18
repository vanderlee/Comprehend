<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Unit\extensions\Abnf\Grammar;

use Tests\Fixtures\Rfc4234TestCase;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\ByteSource;

uses(Rfc4234TestCase::class)->group('library', 'rfc');

test('construct', function () {
    $this->assertNotNull(static::getRfc());
});

test('rfc2234', function (Parser $parser, $input, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input)), (string) $parser);
})->with(Rfc4234TestCase::rfc2234Data());
