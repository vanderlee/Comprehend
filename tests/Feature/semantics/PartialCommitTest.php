<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

test('partial commit failure retains the original exception and never resumes effects', function (string $entry): void {
    $events = [];
    $failure = new RuntimeException('Second effect failed');
    $parser = (new Sequence(
        (new Text('a'))->onCommit(static function () use (&$events): void {
            $events[] = 'first';
        }),
        (new Text('b'))->onCommit(static function () use (&$events, $failure): void {
            $events[] = 'second';
            throw $failure;
        }),
        (new Text('c'))->onCommit(static function () use (&$events): void {
            $events[] = 'third';
        }),
    ))->onCommit(static function () use (&$events): void {
        $events[] = 'parent';
    });
    $grammar = match ($entry) {
        'direct' => $parser,
        'plain' => $parser->freeze(FreezeFlags::NONE),
        'optimized' => $parser->freeze(),
        'facade' => C::of($parser),
    };
    $result = $grammar->parseAll(new ByteSource('abc'));
    expect($result->success)->toBeTrue();
    expect($result->validate()->valid)->toBeTrue();
    expect($result->value())->toBe('abc');
    expect($events)->toBe([]);
    for ($attempt = 0; $attempt < 3; ++$attempt) {
        $caught = null;
        try {
            $result->commit();
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        expect($caught)->toBe($failure);
        expect($events)->toBe(['first', 'second']);
        expect($result->isCommitted())->toBeFalse();
    }
    $fresh = $grammar->parseAll(new ByteSource('abc'));
    expect($fresh->isCommitted())->toBeFalse();
    expect($events)->toBe(['first', 'second']);
    expect(static fn() => $fresh->commit())->toThrow(RuntimeException::class, 'Second effect failed');
    expect($events)->toBe(['first', 'second', 'first', 'second']);
})->with(['direct', 'plain', 'optimized', 'facade']);
