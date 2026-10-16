<?php

declare(strict_types=1);

namespace Tests\Feature\authoring;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\ByteSource;

abstract class ComprehendConvenienceTest extends TestCase
{
    public static function numericCases(): array
    {
        return [
            [C::binary(5, 5), '101', true],
            [C::binary(), '102', false],
            [C::octal(63, 63), '77', true],
            [C::octal(), '8', false],
            [C::hexadecimal(255, 255), 'ff', true],
            [C::hexadecimal(255, 255)->caseSensitive(false), 'FF', true],
            [C::hexadecimal(), 'FF', false],
            [C::octet(), '255', true],
            [C::octet(), '256', false],
            [C::integer(), '007', false],
            [C::integer(7, 7, allowLeadingZeros: true), '007', true],
            [C::binary(5, 5, allowLeadingZeros: true), '00101', true],
            [C::octal(7, 7, allowLeadingZeros: true), '007', true],
            [C::hexadecimal(15, 15, allowLeadingZeros: true), '00f', true],
            [C::octet(allowLeadingZeros: true), '0255', true],
            [C::integer(null, 0, allowLeadingZeros: true), '-00', true],
            [C::integer(2, 2, 2, digits: 'ab'), 'ba', true],
            [C::integer(2, 2, 2, digits: 'αβ'), 'βα', true],
            [C::integer(2, 2, 2, digits: 'ab')->caseSensitive(false), 'BA', true],
            [C::integer(28, 28, 36)->caseSensitive(false), 'ſ', true],
            [C::integer(2, 2, 2, allowLeadingZeros: true, digits: 'αβ'), 'αβα', true],
            [C::integer(null, null), (string) PHP_INT_MIN, true],
            [C::integer(), (string) PHP_INT_MAX, true],
            [C::integer(), (string) PHP_INT_MAX . '0', false],
            [C::integer(null, null), (string) PHP_INT_MIN . '0', false],
        ];
    }

    public static function invalidAlphabets(): array
    {
        return [['aa'], ['-a'], ["\xffa"]];
    }
}

uses(ComprehendConvenienceTest::class);

test('literal composition keeps named syntax and reusable expressions', function (): void {
    $digits = C::range('0', '9')->some()->lexeme()->named('digits');
    $bracketed = C::sequence('[', $digits, ']');
    $grammar = $bracketed->or('none', 'empty');
    $result = $grammar->parse('[123]');
    $this->assertTrue($result->success);
    $this->assertSame('[123]', $result->rawText());
    $this->assertSame('digits', $result->ast()->children[0]->name);
    $this->assertSame('123', $result->ast()->children[0]->rawText());
    $this->assertTrue($grammar->parse('none')->success);
    $this->assertFalse($bracketed->parse('none')->success);
    $this->assertTrue(C::text('a')->then('b', 'c')->parse('abc')->success);
    $this->assertTrue(C::all('ab', C::regex('/ab/'))->parse('ab')->success);
    $this->assertFalse(C::except('ab', 'a')->parse('ab')->success);
    $this->assertTrue(C::not('x')->then('y')->parse('y')->success);
    $this->assertSame('ab', C::longest('a', 'ab')->matchPrefix('abc')->rawText());
    $this->assertSame('a', C::shortest('ab', 'a')->matchPrefix('abc')->rawText());
    $this->assertTrue(C::first(C::nothing(), 'x')->parse('')->success);
});

test('direct parsing has explicit consumption and canonical byte offsets', function (): void {
    $grammar = C::text('é');
    $this->assertTrue($grammar->parse('é')->success);
    $this->assertFalse($grammar->parse('éx')->success);
    $this->assertSame(2, $grammar->matchPrefix('éx')->consumedByteLength);
    $this->assertSame(2, C::text('x')->parseAll('éx', 2)->start);
    $frozen = $grammar->freeze();
    $this->assertTrue($frozen->parseAll('é')->success);
    $this->assertTrue($frozen->matchPrefix('éx')->success);
    $this->assertTrue(C::text("\xff")->parse(new ByteSource("\xff"))->success);
    $this->expectException(InvalidArgumentException::class);
    $grammar->parse("\xff");
});

test('direct parsing freezes current definitions and retains explicit snapshots', function (): void {
    $rules = new Ruleset(['word' => C::text('a')]);
    $word = $rules->rule('word');
    $frozen = $word->freeze();
    $rules->define('word', C::text('b'));
    $this->assertTrue($word->parse('b')->success);
    $this->assertTrue($frozen->parseAll('a')->success);
    $this->assertFalse($frozen->parseAll('b')->success);
    $this->expectException(ParseLimitException::class);
    $word->parse('b', limits: new ParseLimits(maxWork: 1));
});

test('numeric factories', function (C $grammar, string $text, bool $accepted): void {
    $this->assertSame($accepted, $grammar->parse($text)->success);
})->with(ComprehendConvenienceTest::numericCases());

test('numeric prefixes keep range trimming and whole code point spans', function (): void {
    $this->assertSame('25', C::octet()->matchPrefix('256')->rawText());
    $this->assertSame((string) PHP_INT_MAX, C::integer()->matchPrefix((string) PHP_INT_MAX . '0')->rawText());
    $result = C::integer(1, 1, 2, digits: 'αβ')->matchPrefix('βα');
    $this->assertSame('β', $result->rawText());
    $this->assertSame(2, $result->consumedByteLength);
});

test('invalid numeric alphabets are rejected', function (string $digits): void {
    $this->expectException(InvalidArgumentException::class);
    C::integer(base: 2, digits: $digits);
})->with(ComprehendConvenienceTest::invalidAlphabets());

test('case insensitive alphabet cannot have ambiguous digits', function (): void {
    $this->expectException(InvalidArgumentException::class);
    C::integer(base: 2, digits: 'aA')->caseSensitive(false)->parse('A');
});

test('empty literal requires an explicit zero width parser', function (): void {
    $this->expectException(InvalidArgumentException::class);
    C::sequence('');
});

test('custom digit symbols remain whole on byte sources', function (): void {
    $number = C::integer(null, null, 2, digits: 'αβ');
    $this->assertFalse($number->parse(new ByteSource('-α'))->success);
    $this->assertTrue($number->parse(new ByteSource('-βα'))->success);
    $result = $number->matchPrefix(new ByteSource("βα\xff"));
    $this->assertSame('βα', $result->rawText());
    $this->assertSame(4, $result->consumedByteLength);
    $this->assertFalse($number->parse(new ByteSource("\xce"))->success);
});
