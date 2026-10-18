<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Extensions\Extraction\Extraction;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\FoldLeft;
use Vanderlee\Comprehend\Extensions\Extraction\Parser\MapWith;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('mapWith combines in source order lazily and caches its value independently of effects', function (): void {
    $syntax = Extraction::syntax();
    $calls = [];
    $left = $syntax->text('a')->map(static function (string $value) use (&$calls): string {
        $calls[] = 'left';

        return strtoupper($value);
    })->onCommit(static function () use (&$calls): void {
        $calls[] = 'left effect';
    });
    $right = $syntax->text('b')->map(static function (string $value) use (&$calls): string {
        $calls[] = 'right';

        return strtoupper($value);
    })->onCommit(static function () use (&$calls): void {
        $calls[] = 'right effect';
    });
    $parser = $left->mapWith($right, static function (string $a, string $b) use (&$calls): object {
        $calls[] = 'combine';

        return (object) ['text' => $a . $b];
    })->spacing(C::readWhileSet(' '));
    $result = $parser->parse('a b');
    $this->assertTrue($result->success);
    $this->assertSame([], $calls);
    $value = $result->value();
    $this->assertSame('AB', $value->text);
    $this->assertSame($value, $result->value());
    $this->assertSame(['left', 'right', 'combine'], $calls);
    $this->assertSame('a b', $result->rawText());
    $this->assertCount(3, $result->node->children[0]->children);
    $this->assertTrue($result->node->children[0]->children[1]->isTrivia);
    $result->commit();
    $result->commit();
    $this->assertSame(['left', 'right', 'combine', 'left effect', 'right effect'], $calls);
});

test('mapWith accepts literal and low-level operands and composes with mapping', function (): void {
    $item = Extraction::syntax()->text('a');
    $join = static fn(string $a, string $b): string => $a . $b;
    $this->assertSame('ab', $item->mapWith('b', $join)->parse('ab')->value());
    $this->assertSame('AB', $item->mapWith(new Text('b'), $join)->map('strtoupper')->parse('ab')->value());
    $direct = new MapWith(new Text('a'), new Text('b'), $join);
    $this->assertSame('ab', $direct->parseAll(new ByteSource('ab'))->value());
});

test('mapWith never evaluates a failed or abandoned combination', function (): void {
    $bad = Extraction::syntax()->text('a')->mapWith('x', static fn(): never => throw new LogicException('unused'));
    $this->assertFalse($bad->parse('ab')->success);
    $choice = Extraction::syntax()->valueChoice($bad, Extraction::syntax()->text('ab'));
    $this->assertSame('ab', $choice->parse('ab')->value());
});

test('foldLeft is left associative and evaluates steps one at a time', function (int $flags): void {
    $calls = [];
    $number = Extraction::syntax()->integer()->mapText(static function (string $text) use (&$calls): int {
        $calls[] = 'value:' . $text;

        return (int) $text;
    });
    $step = Extraction::syntax()->text('-')->ignoreThen($number);
    $parser = $number->foldLeft($step, static function (int $left, int $right) use (&$calls): int {
        $calls[] = 'combine:' . $right;

        return $left - $right;
    })->freeze($flags);
    $result = $parser->parse('10-3-2');
    $this->assertSame([], $calls);
    $this->assertSame(5, $result->value());
    $this->assertSame(5, $result->value());
    $this->assertSame(['value:10', 'value:3', 'combine:3', 'value:2', 'combine:2'], $calls);
    $this->assertSame('10-3-2', $result->rawText());
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('foldLeft preserves an empty-tail head and isolates accumulators', function (): void {
    $head = Extraction::syntax()->text('x')->map(static fn(): object => (object) ['count' => 0]);
    $fold = $head->foldLeft('y', static function (object $state, string $step): object {
        ++$state->count;

        return $state;
    });
    $empty = $fold->parse('x');
    $value = $empty->value();
    $this->assertSame(0, $value->count);
    $this->assertSame($value, $empty->value());
    $first = $fold->parse('xyy')->value();
    $second = $fold->parse('xyy')->value();
    $this->assertSame(2, $first->count);
    $this->assertSame(2, $second->count);
    $this->assertNotSame($first, $second);
    $this->assertSame(0, $value->count);
});

test('foldLeft bounds count steps rather than the head', function (): void {
    $syntax = Extraction::syntax();
    $head = $syntax->text('x')->map(static fn(): int => 10);
    $step = $syntax->text('y')->map(static fn(): int => 1);
    $add = static fn(int $a, int $b): int => $a + $b;
    $parser = $head->foldLeft($step, $add, min: 1, max: 2);
    $this->assertFalse($parser->parse('x')->success);
    $this->assertSame(11, $parser->parse('xy')->value());
    $this->assertSame(12, $parser->parse('xyy')->value());
    $this->assertFalse($parser->parse('xyyy')->success);
    $prefix = $parser->matchPrefix('xyyy');
    $this->assertSame(3, $prefix->end);
    $this->assertSame(12, $prefix->value());
    $this->assertSame(10, $head->foldLeft($step, $add, max: 0)->parse('x')->value());
});

test('foldLeft preserves null and false heads when no steps match', function (mixed $value): void {
    $head = Extraction::syntax()->text('x')->map(static fn(): mixed => $value);
    $result = $head->foldLeft('y', static fn(): never => throw new LogicException('unused'))->parse('x');
    $this->assertTrue($result->success);
    $this->assertSame($value, $result->value());
})->with([null, false, 0, '']);

test('foldLeft rolls back unsuccessful steps and their trivia and effects', function (int $flags): void {
    $effects = [];
    $syntax = Extraction::syntax();
    $number = $syntax->integer()->mapText('intval')
        ->onCommit(static function (ParseResult $result) use (&$effects): void {
            $effects[] = $result->rawText();
        });
    $minus = $syntax->text('-')->onCommit(static function () use (&$effects): void {
        $effects[] = '-';
    });
    $parser = $number->foldLeft($minus->ignoreThen($number), static fn(int $a, int $b): int => $a - $b)
        ->spacing(C::readWhileSet(' '))->freeze($flags);
    $result = $parser->matchPrefix('10 - 3 - ?');
    $this->assertSame('10 - 3', $result->rawText());
    $this->assertSame(7, $result->value());
    $this->assertSame([], $effects);
    $result->commit();
    $result->commit();
    $this->assertSame(['10', '-', '3'], $effects);
    $this->assertFalse($parser->parse('10 - 3 - ?')->success);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('foldLeft retains required-step diagnostics and lazy validation', function (): void {
    $syntax = Extraction::syntax();
    $number = $syntax->integer()->mapText('intval')->label('number');
    $calls = 0;
    $step = $syntax->text('-')->ignoreThen($number);
    $parser = $number->foldLeft($step, static function (int $a, int $b) use (&$calls): int {
        ++$calls;

        return $a - $b;
    }, min: 1)->validateSemantic(static fn(ParseResult $result): bool => $result->value() > 0);
    $bad = $parser->parse('10-?');
    $this->assertSame(3, $bad->diagnostic->byteOffset);
    $this->assertSame(['number'], $bad->diagnostic->expected);
    $this->assertSame(0, $calls);
    $result = $parser->parse('1-2');
    $this->assertTrue($result->success);
    $this->assertFalse($result->validate()->valid);
    $this->assertFalse($result->commit());
    $this->assertSame(1, $calls);
});

test('pipeline and fold exceptions are cached and outer overrides skip inner evaluation', function (bool $fold): void {
    $syntax = Extraction::syntax();
    $calls = 0;
    $error = new RuntimeException('combine failed');
    $combine = static function () use (&$calls, $error): never {
        ++$calls;
        throw $error;
    };
    $parser = $fold ? $syntax->text('x')->foldLeft('y', $combine) : $syntax->text('x')->mapWith('y', $combine);
    $result = $parser->parse('xy');
    $this->assertSame(0, $calls);
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->value();
            $this->fail('Expected the cached combiner error');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $calls);
    $this->assertSame('xy', $parser->mapText('strval')->parse('xy')->value());
    $this->assertSame(1, $calls);
})->with([false, true]);

test('foldLeft stops evaluating step values when its combiner throws', function (): void {
    $calls = [];
    $error = new RuntimeException('first step failed');
    $head = Extraction::syntax()->text('x')->map(static function () use (&$calls): int {
        $calls[] = 'head';

        return 0;
    });
    $step = Extraction::syntax()->range('0', '9')->mapText(static function (string $text) use (&$calls): int {
        $calls[] = 'step:' . $text;

        return (int) $text;
    });
    $result = $head->foldLeft($step, static function (int $a, int $b) use (&$calls, $error): never {
        $calls[] = 'combine:' . $b;
        throw $error;
    })->parse('x12');
    $this->assertSame([], $calls);
    try {
        $result->value();
        $this->fail('Expected the first combiner to throw');
    } catch (RuntimeException $caught) {
        $this->assertSame($error, $caught);
    }
    $this->assertSame(['head', 'step:1', 'combine:1'], $calls);
});

test('value operations load without an Extraction vocabulary', function (string $method): void {
    $parser = C::text('x')->$method('y', static fn(string $a, string $b): string => $a . $b);
    $this->assertSame('xy', $parser->parse('xy')->value());
})->with(['mapWith', 'foldLeft']);

test('low-level folds use the same recognition runtime', function (): void {
    $parser = new FoldLeft(new Text('x'), new Text('y'), static fn(string $a, string $b): string => $a . $b);
    $this->assertSame('xyy', $parser->parseAll(new ByteSource('xyy'))->value());
});

test('frozen pipelines and folds isolate mutable child builders', function (): void {
    $head = (new Text('x'))->mapValue(static fn(): int => 10);
    $step = (new Text('y'))->mapValue(static fn(): int => 1);
    $add = static fn(int $a, int $b): int => $a + $b;
    $pipeline = (new MapWith($head, $step, $add))->freeze();
    $fold = (new FoldLeft($head, $step, $add))->freeze();
    $head->mapValue(static fn(): int => 100);
    $step->mapValue(static fn(): int => 2);

    $this->assertSame(11, $pipeline->parse('xy')->value());
    $this->assertSame(12, $fold->parse('xyy')->value());
    $this->assertSame(12, $fold->parse('xyy')->value());
    $this->assertSame(104, (new FoldLeft($head, $step, $add))->parseAll(new ByteSource('xyy'))->value());
});

test('fold syntax retains Unicode byte spans and every accepted step', function (int $flags): void {
    $syntax = Extraction::syntax();
    $letter = $syntax->text("\u{e9}");
    $result = $letter->foldLeft($letter, static fn(string $a, string $b): string => $a . $b)
        ->freeze($flags)->parse("\u{e9}\u{e9}\u{e9}");
    $this->assertSame(6, $result->consumedByteLength);
    $this->assertSame("\u{e9}\u{e9}\u{e9}", $result->value());
    $this->assertSame($result->rawText(), $result->node->rawText());
    $steps = $result->node->children[1]->children;
    $this->assertCount(2, $steps);
    $this->assertSame([2, 4], [$steps[0]->span->start, $steps[1]->span->start]);
    $this->assertSame([4, 6], [$steps[0]->span->end, $steps[1]->span->end]);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('foldLeft rejects invalid bounds', function (int $min, ?int $max): void {
    $this->expectException(InvalidArgumentException::class);

    Extraction::syntax()->text('x')->foldLeft('y', static fn(): int => 0, $min, $max);
})->with([[-1, null], [0, -1], [2, 1]]);

test('foldLeft rejects a zero-width step when repetition is enabled', function (): void {
    $this->expectException(LogicException::class);

    $parser = new FoldLeft(new Text('x'), C::nothing()->parser(), static fn(): int => 0);
    $parser->parseAll(new ByteSource('x'));
});
