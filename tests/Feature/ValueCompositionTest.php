<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('application values compose lazily with spacing and explicit effects', function (): void {
    $calls = [];
    $number = Extraction::syntax()->integer()->mapText('intval')->map(function (int $value) use (&$calls): int {
        $calls[] = 'number';
        return $value * 2;
    });
    $parser = Extraction::syntax()->collect(Extraction::syntax()->text('USD'), $number)
        ->map(static fn(array $parts): object => (object) ['currency' => $parts[0], 'amount' => $parts[1]])
        ->onCommit(function () use (&$calls): void {
            $calls[] = 'commit';
        })
        ->spacing(Extraction::syntax()->text(' ')->many());
    $result = $parser->parse('USD 12');
    $this->assertTrue($result->success);
    $this->assertSame([], $calls);
    $this->assertSame('USD 12', $result->rawText());
    $value = $result->value();
    $this->assertSame(24, $value->amount);
    $this->assertSame($value, $result->value());
    $this->assertSame(['number'], $calls);
    $result->commit();
    $result->commit();
    $this->assertSame(['number', 'commit'], $calls);
    $this->assertNotSame($value, $parser->parse('USD 12')->value());
});

test('mapping composes in order and explicit mappings replace the chain', function (): void {
    $base = Extraction::syntax()->text('12')->mapText('intval')->map(static fn(int $n): int => $n + 1);
    $this->assertSame(26, $base->map(static fn(int $n): int => $n * 2)->parse('12')->value());
    $this->assertSame(13, $base->parse('12')->value());
    $this->assertSame('12!', $base->mapText(static fn(string $s): string => $s . '!')->parse('12')->value());
    $this->assertSame(7, $base->mapValue(static fn(ParseResult $r): int => 7)->parse('12')->value());
    $this->assertSame('ABC', Extraction::syntax()->text('abc')->map('strtoupper')->parse('abc')->value());
});

test('mapping failures cache the same exception and do not evaluate later stages', function (): void {
    $calls = 0;
    $error = new RuntimeException('bad value');
    $parser = Extraction::syntax()->text('a')->map(function (string $text) use (&$calls, $error): never {
        ++$calls;
        throw $error;
    })->map(static fn(mixed $value): never => throw new LogicException('unreachable'));
    $result = $parser->parse('a');
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->value();
            $this->fail('Expected cached mapping error');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $calls);
    $this->assertFalse($parser->parse('ab')->success);
    $this->assertNull($parser->parse('x')->value());
    $this->assertSame(1, $calls);
});

test('value choice ignores abandoned mappings while ordinary choices retain text', function (): void {
    $bad = Extraction::syntax()->text('a')->map(static fn(string $s): never => throw new LogicException('discarded'));
    $good = Extraction::syntax()->text('ab')->map(static fn(string $s): int => 2);
    $this->assertSame(2, Extraction::syntax()->valueChoice($bad, $good)
        ->prefer(ChoicePolicy::LONGEST)->parse('ab')->value());
    $this->assertSame('ab', Extraction::syntax()->choice($good)->parse('ab')->value());
    $this->assertSame('ab', Extraction::syntax()->sequence($good)->parse('ab')->value());
});

test('syntax and fluent value factories retain nested operand boundaries', function (): void {
    $syntax = Extraction::syntax();
    $parser = $syntax->text('a')->collect($syntax->collect('b', 'c'));
    $this->assertSame(['a', ['b', 'c']], $parser->parse('abc')->value());
    $this->assertSame(2, $syntax->text('a')->map(static fn(string $s): int => 1)
        ->valueChoice($syntax->text('b')->map(static fn(string $s): int => 2))->parse('b')->value());
    $nil = Extraction::syntax()->text('nil')->map(static fn(string $s): mixed => null);
    $this->assertNull($syntax->valueChoice($nil)->parse('nil')->value());
});

test('value composition retains selected validation and discards abandoned effects', function (): void {
    $effects = [];
    $a = Extraction::syntax()->text('a')->map(static fn(string $s): int => 1)
        ->validateSemantic(static fn(ParseResult $r): bool => $r->value() === 1)
        ->onCommit(function () use (&$effects): void {
            $effects[] = 'a';
        });
    $ab = Extraction::syntax()->text('ab')->map(static fn(string $s): int => 2)
        ->onCommit(function () use (&$effects): void {
            $effects[] = 'ab';
        });
    $result = Extraction::syntax()->valueChoice($a->thenIgnore('x'), $ab)->parse('ab');
    $this->assertSame(2, $result->value());
    $result->commit();
    $this->assertSame(['ab'], $effects);
    $selected = Extraction::syntax()->collect($a, 'b')->parse('ab');
    $selected->commit();
    $this->assertSame(['ab', 'a'], $effects);
    $this->assertSame([1, 'b'], $selected->value());
});

test('frozen value chains survive builder changes and outer overrides skip inner mappings', function (): void {
    $parser = (new Text('a'))->map('strtoupper');
    $frozen = $parser->freeze();
    $result = $parser->parseAll(new ByteSource('a'));
    $parser->map(static fn(string $s): string => $s . '!');
    $this->assertSame('A', $result->value());
    $this->assertSame('A', $frozen->parse('a')->value());
    $bad = Extraction::syntax()->text('a')->map(static fn(string $s): never => throw new LogicException('unused'));
    $this->assertSame('a', Extraction::syntax()->collect($bad)->mapText('strval')->parse('a')->value());
    $this->assertSame('a', Extraction::syntax()->valueChoice($bad)->mapText('strval')->parse('a')->value());
    $this->assertSame('A', Extraction::syntax()->text('a')->map('strtoupper')
        ->between('(', ')')->map('strval')->parse('(a)')->value());
});
