<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Structure\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Source\ByteSource;

test('configured references retain recognition diagnostics trees and effects across entry points', function (
    string $input,
    bool $accepted,
): void {
    $baseline = null;
    foreach (['direct', 'plain', 'optimized', 'facade'] as $entry) {
        $effects = [];
        $rules = new Ruleset(['item' => new Text('a')], 'sample');
        $parser = new Sequence(
            new Choice(
                $rules->ref('item')->guard(static fn(): bool => false)->label('rejected'),
                $rules->ref('item')->name('first')->tag('left')->mapValue(static fn(): string => 'ONE')
                    ->onCommit(static function (ParseResult $result) use (&$effects): void {
                        $effects[] = $result->value();
                    }),
            ),
            $rules->ref('item')->name('second')->tag('right')->mapValue(static fn(): string => 'TWO')
                ->onCommit(static function (ParseResult $result) use (&$effects): void {
                    $effects[] = $result->value();
                }),
        );
        $source = new ByteSource($input);
        $grammar = match ($entry) {
            'direct' => $parser,
            'plain' => $parser->freeze(FreezeFlags::NONE),
            'optimized' => $parser->freeze(),
            'facade' => C::of($parser),
        };
        $result = $grammar->parseAll($source);
        expect($result->success)->toBe($accepted);
        expect($effects)->toBe([]);
        $observation = [
            $result->consumedByteLength, $result->farthestFailure, $result->diagnostic?->byteOffset,
            $result->diagnostic?->message(), $result->node?->toArray(), $result->ast()?->toArray(),
            $result->value(), $result->results(), $result->validate()->valid,
        ];
        $baseline ??= $observation;
        expect($observation)->toEqual($baseline);
        expect($result->commit())->toBe($accepted);
        expect($result->commit())->toBe($accepted);
        expect($effects)->toBe($accepted ? ['ONE', 'TWO'] : []);
    }
})->with([['aa', true], ['ab', false], ['a', false]]);

test('a rejecting second reference blocks all effects across entry points', function (string $entry): void {
    $effects = [];
    $validated = [];
    $rules = new Ruleset(['item' => new Text('a')]);
    $references = [];
    foreach (['first', 'second'] as $name) {
        $references[] = $rules->ref('item')->name($name)->mapValue(static fn(): string => $name)
            ->validateSemantic(static function (ParseResult $result) use (&$validated): bool|string {
                $validated[] = $result->value();

                return $result->value() === 'first' ? true : 'Second reference rejected';
            })->onCommit(static function (ParseResult $result) use (&$effects): void {
                $effects[] = $result->value();
            });
    }
    $parser = new Sequence(...$references);
    $grammar = match ($entry) {
        'direct' => $parser,
        'plain' => $parser->freeze(FreezeFlags::NONE),
        'optimized' => $parser->freeze(),
        'facade' => C::of($parser),
    };
    $result = $grammar->parseAll(new ByteSource('aa'));
    expect($result->success)->toBeTrue();
    expect($validated)->toBe([]);
    expect($result->validate()->valid)->toBeFalse();
    expect($result->validate()->issues[0]->message)->toBe('Second reference rejected');
    expect($result->commit())->toBeFalse();
    expect($result->commit())->toBeFalse();
    expect($validated)->toBe(['first', 'second']);
    expect($effects)->toBe([]);
})->with(['direct', 'plain', 'optimized', 'facade']);

test('reference mapping failures are lazy cached and independent across entry points', function (
    string $entry,
    string $projection,
): void {
    $calls = 0;
    $failure = new RuntimeException('Second reference mapping failed');
    $rules = new Ruleset(['item' => new Text('a')]);
    $mapper = static function () use (&$calls, $failure): never {
        ++$calls;
        throw $failure;
    };
    $second = $rules->ref('item');
    if ($projection === 'value') {
        $second->mapValue($mapper);
    } else {
        $second->setResult('second', $mapper);
    }
    $parser = new ValueSequence(1, $rules->ref('item')->mapValue(static fn(): string => 'ONE')
        ->setResult('first', 'ONE'), $second);
    $grammar = match ($entry) {
        'direct' => $parser,
        'plain' => $parser->freeze(FreezeFlags::NONE),
        'optimized' => $parser->freeze(),
        'facade' => C::of($parser),
    };
    $result = $grammar->parseAll(new ByteSource('aa'));
    expect($result->success)->toBeTrue();
    expect($calls)->toBe(0);
    for ($read = 0; $read < 2; ++$read) {
        try {
            $result->$projection();
            test()->fail('Expected mapping failure');
        } catch (RuntimeException $error) {
            expect($error)->toBe($failure);
        }
    }
    expect($calls)->toBe(1);
})->with(['direct', 'plain', 'optimized', 'facade'])->with(['value', 'results']);
