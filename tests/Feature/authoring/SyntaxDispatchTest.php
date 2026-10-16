<?php

declare(strict_types=1);

namespace Tests\Feature\authoring;

use ArgumentCountError;
use BadMethodCallException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use TypeError;
use UnexpectedValueException;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Terminal\Text;

abstract class SyntaxDispatchTest extends TestCase
{
    public static function invalidCalls(): array
    {
        return [
            [static fn() => C::text(1), TypeError::class],
            [static fn() => C::text(), ArgumentCountError::class],
            [static fn() => C::text('a', 'b'), ArgumentCountError::class],
            [static fn() => C::some(), ArgumentCountError::class],
            [static fn() => C::text('a')->some('b'), ArgumentCountError::class],
            [static fn() => C::misspelled(), BadMethodCallException::class],
            [static fn() => C::text('a')->misspelled(), BadMethodCallException::class],
            [static fn() => C::text('a')->thenText('b'), BadMethodCallException::class],
            [static fn() => C::someOf('a'), BadMethodCallException::class],
            [static fn() => C::syntax()->define('text', static fn() => C::text('a')), InvalidArgumentException::class],
            [static fn() => C::syntax()->define('parse', static fn() => C::text('a')), InvalidArgumentException::class],
            [
                static fn() => C::syntax()->define('not-a-method', static fn() => C::text('a')),
                InvalidArgumentException::class,
            ],
            [static fn() => C::syntax()->define('bad', static fn() => 'text')->bad(), UnexpectedValueException::class],
        ];
    }
}

uses(SyntaxDispatchTest::class);

test('shared terminal names append rather than replace expressions', function (): void {
    $base = C::text('a')->named('prefix');
    $grammar = $base->set('01')->text('!')->end();
    $this->assertTrue($grammar->parse('a1!')->success);
    $this->assertFalse($grammar->parse('1!')->success);
    $this->assertSame('a', $grammar->parse('a1!')->ast()->firstNamed('prefix')->rawText());
    $this->assertTrue($base->parse('a')->success);
    $this->assertFalse($base->parse('a1!')->success);
    $this->assertTrue(C::nothing()->start()->any()->nothing()->end()->parse('é')->success);
    $this->assertTrue(C::text('(')->char('é')->range('a', 'z')->regex('/[0-9]+/')
        ->literalWindow(')')->parse('(éa12)')->success);
    $this->assertTrue(C::text('n:')->integer(
        minimum: 7,
        maximum: 7,
        allowLeadingZeros: true,
    )->parse('n:007')->success);
});

test('static and fluent repetition signatures retain their meaning', function (): void {
    $this->assertTrue(C::some('a')->parse('aa')->success);
    $this->assertFalse(C::some('a')->parse('')->success);
    $this->assertTrue(C::many('a')->parse('')->success);
    $this->assertFalse(C::maybe('a')->parse('aa')->success);
    $this->assertTrue(C::repeat(parser: 'a', min: 2, max: 3)->parse('aaa')->success);
    $this->assertTrue(C::text('a')->repeat(min: 2, max: 3)->parse('aa')->success);
    $this->assertTrue(C::text('a')->some()->parse('aa')->success);
    $this->assertTrue(C::or('a', 'b')->parse('b')->success);
    $this->assertTrue(C::text('a')->or('b')->parse('b')->success);
    $this->assertTrue(C::text('a')->sequence('b', 'c')->parse('abc')->success);
});

test('scoped syntax can start and continue without affecting other scopes', function (): void {
    $syntax = C::syntax()->define('word', static fn(): C => C::range('a', 'z')->some()->lexeme()->named('word'));
    $word = $syntax->word();
    $grammar = $syntax->text('let ')->word()->text(';');
    $this->assertTrue($grammar->parse('let name;')->success);
    $this->assertSame('name', $grammar->parse('let name;')->ast()->firstNamed('word')->rawText());
    $this->assertTrue($word->parse('hello')->success);
    $this->assertFalse($word->parse('hello;')->success);
    $changed = $syntax->define('word', static fn(): C => C::text('different'));
    $this->assertTrue($changed->word()->parse('different')->success);
    $this->assertTrue($grammar->parse('let name;')->success);
    $this->assertTrue($word->tag('token')->some()->text(':')->word()->parse('name:next')->success);
    $this->assertTrue(C::some($word)->text(':')->word()->parse('name:next')->success);
    $this->assertContains('word', $syntax->names());
    $this->expectException(BadMethodCallException::class);
    C::word();
});

test('custom factories forward named arguments and normalize typed results', function (): void {
    $syntax = C::syntax()
        ->define(
            'literal',
            static fn(string $value, bool $upper = false): Text => new Text($upper ? strtoupper($value) : $value),
        )
        ->define('defined', static fn(): Definition => new Definition(C::text('x')));
    $this->assertTrue($syntax->literal(upper: true, value: 'a')->literal(value: 'b')->parse('Ab')->success);
    $this->assertTrue($syntax->call('LITERAL', value: 'a')->defined()->parse('ax')->success);
});

test('factory results use the calling vocabulary while unscoped repetition retains their own', function (): void {
    $original = C::syntax()->define('suffix', static fn(): C => C::text('!'));
    $returned = $original->text('a');
    $caller = C::syntax()
        ->define('suffix', static fn(): C => C::text('?'))
        ->define('borrowed', static fn(): C => $returned)
        ->defineFluent('borrow', static fn(C $item): C => $returned);

    $this->assertTrue($caller->borrowed()->suffix()->parse('a?')->success);
    $this->assertTrue($caller->text('unused')->borrow()->suffix()->parse('a?')->success);
    $this->assertTrue(C::some($returned)->suffix()->parse('aa!')->success);
    $this->assertTrue($returned->suffix()->parse('a!')->success);
});

test('custom fluents can wrap the current expression', function (): void {
    $syntax = C::syntax()->defineFluent(
        'surround',
        static fn(C $item, string $left, string $right): C => C::sequence($left, $item, $right),
    );
    $base = $syntax->range('0', '9')->some()->named('number');
    $wrapped = $base->surround(right: ']', left: '[')->surround('(', ')');
    $result = $wrapped->parse('([12])');
    $this->assertTrue($result->success);
    $this->assertSame('12', $result->ast()->firstNamed('number')->rawText());
    $this->assertTrue($base->parse('12')->success);
    $this->assertFalse($base->parse('[12]')->success);
    $this->expectException(BadMethodCallException::class);
    $syntax->surround('[', ']');
});

test('factories run for every expression and never during parsing', function (): void {
    $calls = 0;
    $syntax = C::syntax()->define('token', static function () use (&$calls): C {
        return C::text((string) ++$calls);
    });
    $first = $syntax->token();
    $second = $syntax->TOKEN();
    $this->assertTrue($first->parse('1')->success);
    $this->assertTrue($second->parse('2')->success);
    $this->assertSame(2, $calls);
    $first->parse('1');
    $this->assertSame(2, $calls);
});

test('statics and fluents with the same name remain independent', function (): void {
    $syntax = C::syntax()
        ->define('token', static fn(string $value): C => C::text($value))
        ->defineFluent('token', static fn(C $item, string $value): C => C::sequence('[', $item, $value, ']'));
    $original = $syntax->token('a');
    $this->assertTrue($original->token('b')->parse('[ab]')->success);
    $changed = $syntax->defineFluent(
        'token',
        static fn(C $item, string $value): C => C::sequence('(', $item, $value, ')'),
    );
    $this->assertTrue($changed->token('a')->token('b')->parse('(ab)')->success);
    $this->assertTrue($original->token('b')->parse('[ab]')->success);
    $this->assertContains('PLUS', array_map(strtoupper(...), $syntax->names()));
    $this->assertTrue($syntax->PLUS('a')->parse('aa')->success);
});

test('callback composition preserves no op identity and scoped directives', function (): void {
    $syntax = C::syntax()->define('suffix', static fn(): C => C::text('!'));
    $base = $syntax->text('a')->caseSensitive(sensitivity: false)->named(leaf: false, name: 'letter');
    $this->assertSame($base, $base->then());
    $this->assertSame($base, $base->or());
    $grammar = $base->suffix();
    $result = $grammar->parse('A!');
    $this->assertTrue($result->success);
    $this->assertSame('A', $result->ast()->firstNamed('letter')->rawText());
    $this->assertFalse($grammar->parse('B!')->success);
    $this->assertTrue($base->parse('A')->success);
});

test('invalid calls are rejected explicitly', function (callable $call, string $exception): void {
    $this->expectException($exception);
    $call();
})->with(SyntaxDispatchTest::invalidCalls());
