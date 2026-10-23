<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\Choice as OptimizedChoice;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteFileSource;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Source\Utf8FileSource;

test('production prefix plans preserve trees diagnostics lifecycle and exact limits across source modes', function () {
    $prefix = str_repeat('a', 40);
    $events = [];
    $choice = (new Choice(
        new Text($prefix . 'x'),
        new Text($prefix . 'y'),
        (new Text($prefix . 'z'))->name('selected'),
        new Text($prefix),
        new Text($prefix . 'z'),
    ))
        ->mapValue(function ($result) use (&$events): string {
            $events[] = 'value';
            return $result->rawText();
        })->onCommit(function () use (&$events): void {
            $events[] = 'commit';
        });
    $choice['named-key'] = new Text($prefix . 'w');
    foreach (ChoicePolicy::cases() as $policy) {
        $choice->setPreference($policy);
        $plain = $choice->freeze(0);
        $optimized = $choice->freeze();
        $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($optimized);
        expect((new ReflectionProperty(OptimizedChoice::class, 'prefix'))->getValue($root))->not->toBeNull();
        foreach (['', $prefix, $prefix . 'z', $prefix . 'w', $prefix . '!', "a\u{e9}"] as $input) {
            $stream = tmpfile();
            fwrite($stream, '>' . $input);
            $file = new Utf8FileSource($stream, 4);
            $byteFile = new ByteFileSource($stream, 4);
            fclose($stream);
            try {
                foreach ([new ByteSource('>' . $input), new Utf8Source('>' . $input), $file, $byteFile] as $source) {
                    foreach (['matchPrefix', 'parseAll'] as $operation) {
                        for ($pass = 0; $pass < 2; ++$pass) {
                            $events = [];
                            $expected = $plain->$operation($source, 1);
                            $actual = $optimized->$operation($source, 1);
                            expect($events)->toBe([])->and($actual->success)->toBe($expected->success)
                                ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                                ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                                ->and($actual->node)->toEqual($expected->node)
                                ->and($actual->ast())->toEqual($expected->ast());
                            expect($actual->value())->toBe($expected->value());
                            if ($actual->success) {
                                $actual->value();
                                expect($events)->toBe(['value', 'value']);
                                $actual->commit();
                                $actual->commit();
                                expect($events)->toBe(['value', 'value', 'commit']);
                            }
                        }
                    }
                }
            } finally {
                $file->close();
                $byteFile->close();
            }
        }
        $limits = [new ParseLimits(maxDepth: 1), new ParseLimits(maxDepth: 2)];
        for ($work = 1; $work < 220; ++$work) {
            $limits[] = new ParseLimits(maxWork: $work);
        }
        foreach ($limits as $limit) {
            $observed = [];
            foreach ([$plain, $optimized] as $grammar) {
                try {
                    $result = $grammar->parseAll(new Utf8Source('>' . $prefix . 'z'), 1, $limit);
                    $observed[] = [$result->success, $result->node?->toArray(), $result->diagnostic?->toArray()];
                } catch (ParseLimitException $error) {
                    $observed[] = [$error->getMessage(), $error->byteOffset];
                }
            }
            expect($observed[1])->toBe($observed[0]);
        }
        $copy = (new GraphSnapshot())->copy($root);
        expect((new ReflectionProperty(OptimizedChoice::class, 'prefix'))->getValue($copy))->toBeNull();
    }
});

test('prefix plans preserve scoped and custom-source recognition after errors', function () {
    $prefix = str_repeat('A', 40);
    $choice = new Choice(...array_map(
        static fn(string $suffix): Text => new Text($prefix . $suffix),
        ['x', 'y', 'z', 'w'],
    ));
    $plain = $choice->freeze(0);
    $optimized = $choice->freeze();
    $rootProperty = new ReflectionProperty(FrozenGrammar::class, 'root');
    foreach ([true, false] as $caseSensitive) {
        foreach ([null, new Text(' ')] as $spacing) {
            $source = new ByteSource($prefix . '!');
            $observations = [];
            foreach ([$plain, $optimized] as $grammar) {
                $metrics = new ParseMetrics();
                $session = new ParseSession(
                    $source,
                    trivia: $spacing,
                    caseSensitive: $caseSensitive,
                    metrics: $metrics,
                );
                $session->recordFailure(0, 'inherited', [['0']]);
                $match = $rootProperty->getValue($grammar)->recognizeInSession($source, 0, $session);
                $observations[] = [$match->length, $session->diagnostic(), $session->workUsed()];
                if (!$caseSensitive || $spacing !== null) {
                    expect($metrics->snapshot()['parser_entries'])->toBe(5);
                }
            }
            expect($observations[1])->toEqual($observations[0]);
        }
    }
    $stream = tmpfile();
    fwrite($stream, $prefix . '!');
    $custom = new class ($stream) extends ByteFileSource {
        // Custom sources retain ordinary terminal reads.
    };
    fclose($stream);
    try {
        $metrics = new ParseMetrics();
        $session = new ParseSession($custom, metrics: $metrics);
        $rootProperty->getValue($optimized)->recognizeInSession($custom, 0, $session);
        expect($metrics->snapshot()['parser_entries'])->toBe(5);
    } finally {
        $custom->close();
    }
    $stream = tmpfile();
    fwrite($stream, $prefix . '!');
    $closed = new Utf8FileSource($stream);
    fclose($stream);
    $closed->close();
    expect(fn() => $optimized->parseAll($closed))->toThrow(LogicException::class);
    expect($optimized->parseAll(new ByteSource($prefix . 'z'))->success)->toBeTrue();
});
