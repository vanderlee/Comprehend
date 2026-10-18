<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Unit\extensions\Network\Grammar;

use Tests\Fixtures\Rfc3986TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Parser;

uses(Rfc3986TestCase::class)->group('library', 'rfc');

test('construct', function () {
    $this->assertNotNull(static::getRfc());
});

test('complete references preserve alternative path forms', function (string $rule, string $input): void {
    $result = static::getRfc()->rule($rule)->parse($input);

    $this->assertTrue($result->success, $input);
    $this->assertSame($input, $result->rawText());
})->with(Rfc3986TestCase::completeReferences());

test('rules', function (Parser $parser, $input, $match, $length) {
    $this->assertResult($match, $length, $parser->matchPrefix(new ByteSource($input)), (string) $parser);
})->with(Rfc3986TestCase::rulesData());
