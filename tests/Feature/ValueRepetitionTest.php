<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Extensions\Binding;
use Vanderlee\Comprehend\Extensions\Binding\ValueRepeat;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;

uses(TestCase::class);

test('repetition retains only selected trivia effects including empty trivia', function (string $input): void {
    $calls = [];
    $trivia = Binding::syntax()->text(' ')->many()->onCommit(static function (ParseResult $result) use (&$calls): void {
        $calls[] = 'trivia:' . $result->rawText();
    });
    $item = Binding::syntax()->text('a')->onCommit(static function () use (&$calls): void {
        $calls[] = 'item';
    });
    $result = $item->manyValues()->spacing($trivia)->matchPrefix($input);
    $this->assertTrue($result->success);
    $this->assertSame(['a', 'a'], $result->value());
    $this->assertSame([], $calls);
    $result->commit();
    $result->commit();
    $this->assertSame(['item', 'trivia:' . ($input === 'aa x' ? '' : ' '), 'item'], $calls);
    $this->assertSame($input === 'aa x' ? 'aa' : 'a a', $result->rawText());
})->with(['aa x', 'a a x']);

test('value repetition factories work through static fluent and syntax authoring', function (): void {
    $syntax = Binding::syntax();
    $item = Binding::syntax()->char('1')->mapText('intval');
    foreach ([$item->manyValues(), Binding::syntax()->manyValues($item), $syntax->manyValues($item)] as $parser) {
        $this->assertSame([1, 1], $parser->parse('11')->value());
        $this->assertSame([], $parser->parse('')->value());
    }
    $nonempty = [
        $item->someValues(), Binding::syntax()->someValues($item),
        $syntax->char('1')->mapText('intval')->someValues(),
    ];
    foreach ($nonempty as $parser) {
        $this->assertFalse($parser->parse('')->success);
        $this->assertSame([1], $parser->parse('1')->value());
    }
    $this->assertSame(['a', 'a'], Binding::syntax()->repeatValues('a', min: 2, max: 2)->parse('aa')->value());
    $this->assertSame([1], $item->repeatValues(min: 1, max: 1)->parse('1')->value());
    $this->assertSame('11', $item->many()->parse('11')->value());
    $this->assertSame('', $item->optional()->parse('')->value());
});

test('optional factory preserves false zero empty and null values', function (mixed $value): void {
    $item = Binding::syntax()->text('x')->map(static fn(string $s): mixed => $value);
    $parsers = [
        $item->optionalValue(default: 'absent'), Binding::syntax()->optionalValue($item, 'absent'),
        Binding::syntax()->optionalValue($item, 'absent'),
    ];
    foreach ($parsers as $parser) {
        $this->assertSame($value, $parser->parse('x')->value());
        $this->assertSame('absent', $parser->parse('')->value());
    }
})->with([false, 0, '', null]);

test('repeated values exclude trivia and rollback failed extension syntax and effects', function (): void {
    $calls = [];
    $item = Binding::syntax()->text('é')->map(function (string $s) use (&$calls): string {
        $calls[] = 'value';
        return $s;
    })->validateSemantic(static fn(ParseResult $r): bool => $r->value() === 'é')
        ->onCommit(function () use (&$calls): void {
            $calls[] = 'commit';
        });
    $parser = $item->manyValues()->spacing(Binding::syntax()->text(' ')->many());
    $result = $parser->matchPrefix('é é x');
    $this->assertSame([], $calls);
    $this->assertSame('é é', $result->rawText());
    $this->assertSame(5, $result->consumedByteLength);
    $this->assertSame(['é', 'é'], $result->value());
    $this->assertSame(['é', 'é'], $result->value());
    $result->commit();
    $result->commit();
    $this->assertSame(['value', 'value', 'commit', 'commit'], $calls);
    $this->assertCount(3, $result->node->children[0]->children);
    $this->assertFalse($parser->parse('é é x')->success);
});

test('abandoned optional attempts do not retain mappings validators or effects', function (): void {
    $bad = Binding::syntax()->text('a')->map(static fn(string $s): never => throw new LogicException('discarded'))
        ->validateSemantic(static fn(): never => throw new LogicException('discarded validator'))
        ->onCommit(static fn(): never => throw new LogicException('discarded effect'));
    $parser = Binding::syntax()->collect($bad->thenIgnore('!')->optionalValue('missing'), 'a');
    $result = $parser->parse('a');
    $this->assertSame(['missing', 'a'], $result->value());
    $result->commit();
    $this->assertSame('a', $result->rawText());
});

test('repeated and optional mapping errors are lazy cached and replaceable', function (bool $optional): void {
    $calls = 0;
    $error = new RuntimeException('bad item');
    $item = Binding::syntax()->text('a')->map(function (string $s) use (&$calls, $error): never {
        ++$calls;
        throw $error;
    });
    $parser = $optional ? $item->optionalValue('missing') : $item->manyValues();
    $result = $parser->parse('a');
    $this->assertSame(0, $calls);
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->value();
            $this->fail('Expected cached error');
        } catch (RuntimeException $caught) {
            $this->assertSame($error, $caught);
        }
    }
    $this->assertSame(1, $calls);
    $this->assertSame('a', $parser->mapText('strval')->parse('a')->value());
})->with([false, true]);

test('value repetition snapshots detach operands and retain independent result caches', function (): void {
    $item = new Text('a');
    $parser = new ValueRepeat($item);
    $frozen = $parser->freeze();
    $result = $parser->parseAll(new ByteSource('aa'));
    $item->map(static fn(string $s): int => 1);
    $this->assertSame(['a', 'a'], $result->value());
    $this->assertSame(['a'], $frozen->parse('a')->value());
    $this->assertSame([1], $parser->parseAll(new ByteSource('a'))->value());
    $object = Binding::syntax()->text('a')->map(static fn(string $s): object => new stdClass())->manyValues();
    $this->assertNotSame($object->parse('a')->value()[0], $object->parse('a')->value()[0]);
});
