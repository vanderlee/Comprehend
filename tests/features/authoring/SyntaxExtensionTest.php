<?php

declare(strict_types=1);

namespace Tests\features\authoring;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Syntax\Extension\AsciiIdentifiers;

uses(TestCase::class);

test('optional keyword boundaries run before spacing and identifiers stay contiguous', function (): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $parser = $syntax->asciiKeyword('let')->ignoreThen($syntax->asciiIdentifier()->named('name'))
        ->thenIgnore(';')->spacing(C::text(' ')->many());
    $result = $parser->parse('let name;');
    $this->assertSame('name', $result->value());
    $this->assertSame('let name;', $result->rawText());
    $this->assertSame('name', $result->ast()->firstNamed('name')->rawText());
    $this->assertFalse($parser->parse('letname;')->success);
    $this->assertFalse($parser->parse('let na me;')->success);
    $this->assertFalse($parser->parse('let name')->success);
});

test('extension expressions retain their vocabulary when chained and frozen in rulesets', function (): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $parser = $syntax->text('(')->asciiIdentifier()->text(')');
    $this->assertTrue($parser->parse('(name)')->success);
    $rules = new Ruleset();
    $rules->define('name', $syntax->asciiIdentifier())->root('name');
    $frozen = $rules->freeze();
    $this->assertSame('name', $frozen->parse('name')->value());
    $this->assertFalse($frozen->parse('2name')->success);
    $this->assertSame('if', $syntax->asciiKeyword(word: 'if')->parse('if')->value());
});

test('keyword spelling inherits case policy while ascii character membership stays fixed', function (): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $this->assertSame('IF', $syntax->asciiKeyword('if')->caseSensitive(false)->parse('IF')->value());
    $identifier = $syntax->asciiIdentifier()->caseSensitive(false);
    $this->assertSame('NAME', $identifier->parse('NAME')->value());
    $this->assertFalse($identifier->parse('K')->success);
    $this->assertSame('name', $identifier->matchPrefix('nameK')->value());
    $this->assertFalse($syntax->asciiKeyword('if')->caseSensitive(false)->matchPrefix('IF_')->success);
    $this->assertSame('IF', $syntax->asciiKeyword('if')->caseSensitive(false)->matchPrefix('IFK')->value());
});

test('extension parser values and explicit effects retain their normal lifecycle', function (): void {
    $syntax = C::syntax()->extend(new AsciiIdentifiers());
    $mapped = 0;
    $effects = [];
    $keyword = $syntax->asciiKeyword('let')->onCommit(static function (ParseResult $result) use (&$effects): void {
        $effects[] = $result->rawText();
    });
    $name = $syntax->asciiIdentifier()->mapText(static function (string $text) use (&$mapped): string {
        ++$mapped;
        return strtoupper($text);
    })->onCommit(static function (ParseResult $result) use (&$effects): void {
        $effects[] = $result->rawText();
    });
    $result = $keyword->ignoreThen($name)->spacing(C::text(' '))->parse('let name');
    $result->ast();
    $this->assertSame(0, $mapped);
    $this->assertSame('NAME', $result->value());
    $this->assertSame('NAME', $result->value());
    $this->assertSame(1, $mapped);
    $this->assertSame([], $effects);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['let', 'name'], $effects);
});

test('extension parsers use cooperative work accounting', function (): void {
    $this->expectException(ParseLimitException::class);
    C::syntax()->extend(new AsciiIdentifiers())->asciiIdentifier()
        ->parse('name', limits: new ParseLimits(maxWork: 1));
});
