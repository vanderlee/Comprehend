<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

use Tests\Fixtures\Rfc3513TestCase;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Source\ByteSource;

uses(Rfc3513TestCase::class)->group('library', 'rfc');

test('construct', function () {
    $this->assertNotNull(static::getRfc());
});

test('rfc3513', function (Parser $parser, $input, $match, $length) {
    $this->assertResult(
        $match,
        $length,
        $parser->matchPrefix(new ByteSource($input)),
        $parser . ' == `' . $input . '`',
    );
})->with(Rfc3513TestCase::rfc3513Data());
