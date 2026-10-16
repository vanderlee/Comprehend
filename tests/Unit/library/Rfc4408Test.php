<?php

/** @noinspection PhpUndefinedFieldInspection */

namespace Tests\Src\Library;

use Tests\Fixtures\Rfc4408TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Parser\Parser;

uses(Rfc4408TestCase::class)->group('library', 'rfc');

test('construct', function () {
    $this->assertNotNull(static::getRfc());
});

test('complete records resolve and freeze', function (string $input, bool $accepted): void {
    $result = static::getRfc()->freeze()->parse($input);

    $this->assertSame($accepted, $result->success, $input);
})->with(Rfc4408TestCase::completeRecords());

test('explanation repetition always consumes input', function (): void {
    $grammar = static::getRfc()->rule('explain_string')->freeze();

    $this->assertTrue($grammar->parse('')->success);
    $this->assertTrue($grammar->parse('Denied %{i} %% message')->success);
});

test('rules', function (Parser $parser, $input, $match, $length) {
    $this->assertResult(
        $match,
        $length,
        $parser->matchPrefix(new ByteSource($input)),
        (string) $parser . ' <=> ' . $input,
    );
})->with(Rfc4408TestCase::rulesData());
