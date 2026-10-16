<?php

declare(strict_types=1);

namespace Tests\Extensions;

use BadMethodCallException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Extensions\AsciiIdentifiers;

uses(TestCase::class);

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

test('extension names remain optional and the original vocabulary stays untouched', function (): void {
    $base = C::syntax();
    $syntax = $base->extend(new AsciiIdentifiers());
    $this->assertNotContains('asciiidentifier', $base->names());
    $this->assertContains('asciiidentifier', $syntax->names());
    $this->assertSame('x', $syntax->asciiIdentifier()->parse('x')->value());
    $this->expectException(BadMethodCallException::class);
    C::asciiIdentifier();
});

test('ascii scanners preserve prefix offsets and work on byte sources', function (): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $result = $syntax->asciiIdentifier()->matchPrefix('éname!', byteOffset: 2);
    $this->assertSame('name', $result->value());
    $this->assertSame([2, 6], [$result->node->span->start, $result->node->span->end]);
    $this->assertSame('name', $syntax->asciiIdentifier()->matchPrefix(new ByteSource("name\xff"))->value());
    $this->assertSame('if', $syntax->asciiKeyword('if')->matchPrefix(new ByteSource("if\xff"))->value());
});
