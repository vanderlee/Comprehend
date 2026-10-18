<?php

declare(strict_types=1);

namespace Tests\Extensions;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Extensions\AsciiIdentifiers\AsciiIdentifiers;

uses(TestCase::class);

test('ascii fluents append tokens while preserving spacing boundaries and the original expression', function (): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $prefix = $syntax->text('@');
    $declaration = $prefix->asciiKeyword(word: 'let')->asciiIdentifier()
        ->spacing(C::text(' ')->many());
    $result = $declaration->parse('@let name');
    $this->assertTrue($result->success);
    $this->assertSame('@let name', $result->rawText());
    $this->assertSame('@let name', $result->value());
    $this->assertFalse($declaration->parse('@letname')->success);
    $this->assertFalse($declaration->parse('@let na me')->success);
    $this->assertTrue($prefix->parse('@')->success);
    $this->assertFalse($prefix->parse('@let name')->success);
    $this->assertSame('name', $syntax->asciiIdentifier()->parse('name')->value());
    $this->assertTrue($declaration->parse(new ByteSource('@let name'))->success);
});

test('ascii keyword fluent rejects invalid words during authoring', function (): void {
    $this->expectException(InvalidArgumentException::class);
    C::syntax()->extend(new AsciiIdentifiers())->text('@')->asciiKeyword(word: 'bad-word');
});

test('ascii identifiers accept only their explicit alphabet', function (string $input, bool $accepted): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $result = $syntax->asciiIdentifier()->parse($input);
    $this->assertSame($accepted, $result->success);
    $this->assertSame($accepted ? $input : null, $result->value());
})->with([
    ['name', true], ['_name2', true], ['_', true], ['Z9', true], ['let', true],
    ['', false], ['2name', false], ['name-value', false], ['é', false], ['K', false],
]);

test('ascii keywords reject identifier continuations but allow punctuation and eof', function (
    string $input,
    bool $accepted,
): void {
    $parser = C::syntax()->extend(new AsciiIdentifiers())->asciiKeyword('if');
    $result = $parser->matchPrefix($input);
    $this->assertSame($accepted, $result->success);
    $this->assertSame($accepted ? 'if' : null, $result->value());
})->with([
    ['if', true], ['if(', true], ['if name', true], ['ifdef', false],
    ['if2', false], ['if_', false], ['IF', false], ['ifé', true],
]);

test('ascii keyword validates authoring text', function (string $word): void {
    $this->expectException(InvalidArgumentException::class);
    C::syntax()->extend(new AsciiIdentifiers())->asciiKeyword($word);
})->with(['', '2name', 'else-if', 'é', "if\n"]);

test('ascii factories load automatically and explicit installation remains supported', function (): void {
    $base = C::syntax();
    $syntax = $base->extend(new AsciiIdentifiers());
    $this->assertContains('asciiidentifier', $base->names());
    $this->assertContains('asciiidentifier', $syntax->names());
    $this->assertSame('x', $syntax->asciiIdentifier()->parse('x')->value());
    $this->assertSame('x', C::asciiIdentifier()->parse('x')->value());
});

test('ascii scanners preserve prefix offsets and work on byte sources', function (): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $result = $syntax->asciiIdentifier()->matchPrefix('éname!', byteOffset: 2);
    $this->assertSame('name', $result->value());
    $this->assertSame([2, 6], [$result->node->span->start, $result->node->span->end]);
    $this->assertSame('name', $syntax->asciiIdentifier()->matchPrefix(new ByteSource("name\xff"))->value());
    $this->assertSame('if', $syntax->asciiKeyword('if')->matchPrefix(new ByteSource("if\xff"))->value());
});
