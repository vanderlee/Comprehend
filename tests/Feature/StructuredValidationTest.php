<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\ValidationIssue;
use Vanderlee\Comprehend\Source\ByteSource;

test('semantic validation normalizes outcomes and caches issues before blocking effects', function (): void {
    $calls = 0;
    $effects = 0;
    $result = C::text('abc')->validateSemantic(function (ParseResult $result) use (&$calls): array {
        ++$calls;
        return [new ValidationIssue('range', 'Too large', $result->span, ['maximum' => 2])];
    })->validateSemantic(fn() => false)->validateSemantic(fn() => 'custom')
        ->validateSemantic(fn() => true)->validateSemantic(fn() => [])
        ->onCommit(function () use (&$effects): void {
            ++$effects;
        })->parse('abc');
    expect($calls)->toBe(0);
    $report = $result->validate();
    expect($report->valid)->toBeFalse()
        ->and(array_column($report->issues, 'code'))->toBe(['range', 'validation.failed', 'validation.failed'])
        ->and(array_column($report->issues, 'message'))->toBe(['Too large', 'Semantic validation failed', 'custom'])
        ->and($report->issues[0]->span)->toBe($result->span)
        ->and($result->validate())->toBe($report)->and($result->commit())->toBeFalse()
        ->and($calls)->toBe(1)->and($effects)->toBe(0);
});

test('structured validation aggregates children and trivia before their parent', function (): void {
    $parser = C::sequence(
        C::text('a')->validateSemantic(fn() => 'first'),
        C::text('b')->validateSemantic(fn() => 'second'),
    )->spacing(C::text(' ')->validateSemantic(fn() => 'trivia'))->validateSemantic(fn() => 'parent');
    expect(array_column($parser->parse('a b')->validate()->issues, 'message'))
        ->toBe(['first', 'trivia', 'second', 'parent']);
});

test('issues may identify another field in the same source using byte offsets', function (): void {
    $result = C::sequence(C::text('é'), C::text('b')->validateSemantic(
        fn(ParseResult $result) => new ValidationIssue('related', 'Earlier field', $result->span->source->span(0, 2)),
    ))->parse('éb');
    expect($result->validate()->issues[0]->span->rawText())->toBe('é')
        ->and($result->validate()->issues[0]->toArray()['span'])->toBe(['start' => 0, 'end' => 2]);
});

test('malformed validation and foreign sources throw cached exceptions', function (mixed $outcome): void {
    $calls = 0;
    $result = C::text('a')->validateSemantic(function () use ($outcome, &$calls): mixed {
        ++$calls;
        return $outcome;
    })->parse('a');
    $caught = null;
    for ($i = 0; $i < 2; ++$i) {
        try {
            $result->validate();
            test()->fail('Malformed validation must throw');
        } catch (UnexpectedValueException $error) {
            if ($caught !== null) {
                expect($error)->toBe($caught);
            }
            $caught = $error;
        }
    }
    expect($calls)->toBe(1);
})->with([null, 1, [['bad']], [['key' => 'bad']],
    new ValidationIssue('foreign', 'Foreign', (new ByteSource('a'))->span(0, 1))]);

test('failed syntax exposes a cached syntax issue at the diagnostic offset', function (): void {
    $result = C::sequence('a', 'b')->parse('ax');
    $report = $result->validate();
    expect($report->valid)->toBeFalse()->and($report->issues[0]->code)->toBe('syntax.rejected')
        ->and($report->issues[0]->span->start)->toBe(1)->and($report->issues[0]->span->end)->toBe(1)
        ->and($result->validate())->toBe($report)->and($result->commit())->toBeFalse();
});

test('semantic rejection keeps the selected alternative and discarded validators never run', function (): void {
    $discarded = C::text('a')->validateSemantic(fn() => throw new LogicException('Discarded validator'));
    $result = C::choice($discarded->then('x'), C::text('ab')->validateSemantic(fn() => 'selected'))
        ->parse('ab');
    expect(array_column($result->validate()->issues, 'message'))->toBe(['selected']);
});
