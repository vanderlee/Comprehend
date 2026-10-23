<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('flat compiled failures preserve progress and ordered diagnostics at every operand boundary', function () {
    foreach (ChoicePolicy::cases() as $policy) {
        $parser = (new Choice(
            new Sequence((new Text('abcd'))->name('prefix'), new Text('EF'), new Text('gh')),
            new Sequence(new Text('abcd'), new Text('EX'), new Text('ij')),
        ))->setPreference($policy);
        $plain = $parser->freeze(FreezeFlags::NONE);
        $optimized = $parser->freeze();
        $roots = array_map(
            static function (FrozenGrammar $grammar) {
                return (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
            },
            [$plain, $optimized],
        );
        $inputs = ['abcdEFgh', 'abcdEXij', 'abcdEFgh!', "abcdEF\u{e9}"];
        for ($position = 0; $position < 8; ++$position) {
            $inputs[] = substr('abcdEFgh', 0, $position);
            $inputs[] = substr_replace('abcdEFgh', '?', $position, 1);
        }
        foreach ($inputs as $input) {
            foreach ([new ByteSource('>' . $input), new Utf8Source('>' . $input)] as $source) {
                for ($pass = 0; $pass < 2; ++$pass) {
                    $observations = [];
                    foreach ($roots as $root) {
                        $session = new ParseSession($source);
                        $session->pushRule('caller');
                        $session->recordFailure(1, 'inherited');
                        $match = $root->recognizeInSession($source, 1, $session);
                        $observations[] = [$match->accepted, $match->length, $match->diagnostic, $match->node];
                        expect($session->rulePath())->toBe(['caller']);
                    }
                    expect($observations[1])->toEqual($observations[0]);
                    $expected = $plain->parseAll($source, 1);
                    $actual = $optimized->parseAll($source, 1);
                    expect($actual->success)->toBe($expected->success)
                        ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                        ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                        ->and($actual->ast())->toEqual($expected->ast())
                        ->and($actual->value())->toBe($expected->value());
                }
            }
        }
    }
});

test('flat failure skips successful operand entries while retaining their logical work', function () {
    $parser = new Sequence(new Text('ab'), new Text('cd'), new Text('EF'));
    $source = new ByteSource('abcdX');
    $observations = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $grammar = $parser->freeze($flags);
        $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
        $metrics = new ParseMetrics();
        $session = new ParseSession($source, metrics: $metrics);
        $match = $root->recognizeInSession($source, 0, $session);
        $observations[] = [$match, $metrics->snapshot()['parser_entries'], $session->workUsed()];
    }
    expect($observations[1][0]->diagnostic)->toEqual($observations[0][0]->diagnostic)
        ->and($observations[1][0]->length)->toBe(4)
        ->and($observations[0][1])->toBe(4)
        ->and($observations[1][1])->toBe(3)
        // Original work plus the compiled entry, four matching units and one mismatch.
        ->and($observations[1][2])->toBe($observations[0][2] + 6);
});

test('flat failure keeps file offsets and ordinary exhausted work and depth limits', function () {
    $stream = tmpfile();
    if ($stream === false) {
        throw new RuntimeException('Could not create literal fixture');
    }
    fwrite($stream, '>abcdX');
    $source = new Utf8FileSource($stream, 4);
    fclose($stream);
    try {
        $parser = (new Sequence(new Text('ab'), new Text('cd'), new Text('EF')))->label('record');
        $plain = $parser->freeze(FreezeFlags::NONE);
        $optimized = $parser->freeze();
        $limits = [null];
        for ($work = 1; $work <= 12; ++$work) {
            $limits[] = new ParseLimits(maxWork: $work);
        }
        foreach ([1, 2, 3] as $depth) {
            $limits[] = new ParseLimits(maxDepth: $depth);
        }
        foreach ($limits as $limit) {
            $observations = [];
            foreach ([$plain, $optimized] as $grammar) {
                try {
                    $result = $grammar->parseAll($source, 1, $limit);
                    $observations[] = [$result->success, $result->diagnostic, $result->consumedByteLength];
                } catch (ParseLimitException $error) {
                    $observations[] = [$error->getMessage(), $error->byteOffset];
                }
            }
            expect($observations[1])->toEqual($observations[0]);
        }
    } finally {
        $source->close();
    }
});
