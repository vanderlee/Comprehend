<?php

declare(strict_types=1);

namespace Tests\features\semantics;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('fluent selection composes delimiters and lists without changing raw text', function (): void {
    $number = C::integer()->mapText(static fn(string $text): int => (int) $text);
    $list = $number->separatedBy(',')->between('[', ']');
    $this->assertSame([10, 20], $list->parse('[10,20]')->value());
    $this->assertSame('[10,20]', $list->parse('[10,20]')->rawText());
    $this->assertSame([], $list->parse('[]')->value());
    $this->assertSame(42, $number->thenIgnore(';')->parse('42;')->value());
    $this->assertSame(42, C::text('return:')->ignoreThen($number)->parse('return:42')->value());
    $this->assertSame('hello', C::text('hello')->between('(', ')')->between('[', ']')
        ->thenIgnore(';')->parse('[(hello)];')->value());
});

test('selection retains named syntax and utf8 byte spans with inserted trivia', function (): void {
    $parser = C::text('é')->named('body')->between(
        C::text('(')->named('open'),
        C::text(')')->named('close'),
    )->spacing(C::text(' '));
    $result = $parser->parse('( é )');
    $this->assertSame('é', $result->value());
    $this->assertSame('( é )', $result->rawText());
    $this->assertSame(6, $result->consumedByteLength);
    $this->assertSame(['(', ' ', 'é', ' ', ')'], array_map(
        static fn($node): string => $node->rawText(),
        $result->node->children[0]->children,
    ));
    $this->assertSame([2, 4], [
        $result->ast()->firstNamed('body')->span->start,
        $result->ast()->firstNamed('body')->span->end,
    ]);
    $this->assertSame(['open', 'body', 'close'], array_map(
        static fn($node): string => $node->name,
        $result->ast()->children,
    ));
});

test('selection keeps child values lazy and cached while explicit outer mappings replace selection', function (): void {
    $calls = 0;
    $body = C::text('a')->mapText(static function () use (&$calls): object {
        ++$calls;
        return new stdClass();
    });
    $ignored = C::text('!')->mapText(static function (): never {
        throw new RuntimeException('Unselected mapper');
    });
    $parser = $body->thenIgnore($ignored);
    $result = $parser->parse('a!');
    $result->ast();
    $this->assertSame(0, $calls);
    $this->assertSame($result->value(), $result->value());
    $this->assertSame(1, $calls);
    $this->assertTrue($result->commit());
    $this->assertSame(1, $calls);
    $this->assertSame('A!', $parser->mapText('strtoupper')->parse('a!')->value());
    $this->assertSame(1, $calls);
    $this->assertNotSame($result->value(), $parser->parse('a!')->value());
    $this->assertSame(2, $calls);
});

test('selected null and mapping exceptions retain their normal caches', function (): void {
    $this->assertNull(C::text('a')->mapText(static fn() => null)->between('(', ')')->parse('(a)')->value());
    $error = new RuntimeException('selected mapper');
    $calls = 0;
    $parser = C::text('a')->mapText(static function () use (&$calls, $error): never {
        ++$calls;
        throw $error;
    })->between('(', ')');
    $result = $parser->parse('(a)');
    for ($read = 0; $read < 2; ++$read) {
        try {
            $result->value();
            $this->fail('Expected selected mapper exception');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $calls);
});

test('all operands retain collections validation and ordered explicit commit effects', function (): void {
    $effects = [];
    $operand = static function (string $text) use (&$effects): C {
        return C::text($text)->pushResult('parts')->onCommit(
            static function (ParseResult $result) use (&$effects): void {
                $effects[] = $result->rawText();
            },
        );
    };
    $result = $operand('a')->between($operand('('), $operand(')'))->parse('(a)');
    $this->assertSame('a', $result->value());
    $this->assertSame(['parts' => ['(', 'a', ')']], $result->results());
    $this->assertSame([], $effects);
    $this->assertTrue($result->commit());
    $this->assertTrue($result->commit());
    $this->assertSame(['(', 'a', ')'], $effects);
    $result = C::text('a')->between('(', C::text(')')->validateSemantic(static fn() => 'bad close'))
        ->parse('(a)');
    $this->assertSame('a', $result->value());
    $this->assertSame(['bad close'], $result->validate()->errors);
    $this->assertFalse($result->commit());
});

test('discarded selections never evaluate mappings or commit effects', function (): void {
    $calls = 0;
    $bad = static function () use (&$calls): never {
        ++$calls;
        throw new RuntimeException('Discarded callback');
    };
    $branch = C::text('a')->mapText($bad)->onCommit($bad)->thenIgnore('!');
    $result = $branch->or(C::text('a')->thenIgnore('?'))->parse('a?');
    $this->assertSame('a?', $result->value());
    $this->assertTrue($result->commit());
    $this->assertSame(0, $calls);
    $this->assertFalse($branch->parse('a')->commit());
});

test('selection boundaries survive sequence flattening and preserve ordinary then behavior', function (): void {
    $selected = C::sequence('a', 'b')->thenIgnore('!');
    $this->assertSame('ab', $selected->parse('ab!')->value());
    $composed = $selected->then('?')->parse('ab!?');
    $this->assertSame('ab!?', $composed->value());
    $this->assertSame('ab!', $composed->node->children[0]->rawText());
    $this->assertSame('ab', $selected->thenIgnore('?')->parse('ab!?')->value());
});

test('syntax and ruleset authoring use the same value selection', function (): void {
    $syntax = C::syntax()->define('word', static fn(): C => C::text('a'));
    $parser = $syntax->word()->between('(', ')')->thenIgnore(';');
    $rules = new Ruleset();
    $rules->define('value', $parser)->root('value');
    $this->assertSame('a', $rules->freeze()->parse('(a);')->value());
    $this->assertTrue($parser->freeze() === $parser->freeze());
});

test('selection honors spacing overrides and restores scope on exceptions', function (): void {
    $parser = C::text('a')->between('(', ')');
    $this->assertSame('a', $parser->parse('( a )', spacing: C::text(' '))->value());
    $this->assertFalse($parser->spacing(null)->parse('( a )', spacing: C::text(' '))->success);
    $this->assertFalse($parser->lexeme()->parse('( a )', spacing: C::text(' '))->success);
    $source = new ByteSource('(a)');
    $session = new ParseSession($source);
    $body = (new Text('a'))->guard(static function (): never {
        throw new RuntimeException('guard');
    });
    $throwing = (new ValueSequence(1, new Text('('), $body, new Text(')')))
        ->spacing(C::text(' ')->many()->parser());
    try {
        $throwing->recognizeInSession($source, 0, $session);
        $this->fail('Expected guard exception');
    } catch (RuntimeException $caught) {
        $this->assertSame('guard', $caught->getMessage());
    }
    $spacing = null;
    $this->assertSame(0, $session->skipSpacing($source, 0, $spacing));
    $this->assertNull($spacing);
});

test('selection delegates through the shared work budget', function (): void {
    $this->expectException(ParseLimitException::class);
    C::text('a')->between('(', ')')->parse('(a)', limits: new ParseLimits(maxWork: 2));
});
