<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('choice dispatch preserves ordered and ranked choices with unknown and nullable alternatives', function () {
    foreach (ChoicePolicy::cases() as $policy) {
        $prefix = str_repeat('a', 40);
        $parser = (new Choice(
            (new Text('z' . $prefix))->diagnosticRule('0'),
            (new Text($prefix . 'b'))->name('first'),
            (new Text($prefix . 'b'))->name('tie'),
            new Text($prefix),
            new Sequence(new Text('?'), new Text('!')),
            new Nothing(),
        ))->setPreference($policy);
        $parser['named-key'] = new Text('x' . $prefix);
        $plain = $parser->freeze(FreezeFlags::NONE);
        $optimized = $parser->freeze();
        foreach (['', '!', '?!', 'x' . $prefix, $prefix, $prefix . 'b', $prefix . 'c', "\u{e9}"] as $text) {
            foreach ([new ByteSource('>' . $text), new Utf8Source('>' . $text)] as $source) {
                for ($pass = 0; $pass < 2; ++$pass) {
                    foreach (['matchPrefix', 'parseAll'] as $operation) {
                        $expected = $plain->$operation($source, 1);
                        $actual = $optimized->$operation($source, 1);
                        expect($actual->success)->toBe($expected->success)
                            ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                            ->and($actual->farthestFailure)->toBe($expected->farthestFailure)
                            ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                            ->and($actual->node)->toEqual($expected->node)
                            ->and($actual->ast())->toEqual($expected->ast())
                            ->and($actual->value())->toBe($expected->value());
                    }
                }
            }
        }
    }
});

test('rejected long literals avoid checked entries without changing logical work or failure progress', function () {
    foreach (ChoicePolicy::cases() as $policy) {
        $parser = new Choice(new Text(str_repeat('a', 64)), new Text(str_repeat('b', 64)));
        foreach (['c', str_repeat('a', 32) . '!'] as $input) {
            $observations = [];
            $entries = [];
            $source = new ByteSource($input);
            foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
                $grammar = $parser->freeze($flags);
                $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
                $metrics = new ParseMetrics();
                $session = new ParseSession($source, metrics: $metrics);
                $session->pushRule('caller');
                $session->pushPreference($policy);
                $match = $root->recognizeInSession($source, 0, $session);
                $observations[] = [$match->accepted, $match->length, $match->diagnostic, $session->workUsed()];
                $entries[] = $metrics->snapshot()['parser_entries'];
                expect($session->getPreference())->toBe($policy)->and($session->rulePath())->toBe(['caller']);
            }
            expect($observations[1])->toEqual($observations[0])
                ->and($entries[0])->toBe(3)
                ->and($entries[1])->toBe(2);
        }
    }
});

test('choice dispatch preserves guards cached values and explicit selected effects', function () {
    $events = [];
    $literal = str_repeat('a', 40);
    $parser = Choice::first(
        new Text('z' . $literal),
        new Text('y' . $literal),
        (new Text($literal))->guard(static function () use (&$events): bool {
            $events[] = 'reject';
            return false;
        }),
        (new Text($literal))->setResult('selected', static function () use (&$events): int {
            $events[] = 'value';
            return 7;
        })->onCommit(static function () use (&$events): void {
            $events[] = 'commit';
        }),
    )->mapValue(static fn(ParseResult $result): mixed => $result->result('selected'));
    $grammar = $parser->freeze();
    expect($events)->toBe([]);
    for ($pass = 0; $pass < 2; ++$pass) {
        $events = [];
        $result = $grammar->parseAll($literal);
        expect($result->success)->toBeTrue()->and($events)->toBe(['reject']);
        expect($result->value())->toBe(7)->and($result->value())->toBe(7);
        $result->commit();
        $result->commit();
        expect($events)->toBe(['reject', 'value', 'commit']);
    }
});

test('dispatch preserves bounded lexical hits and clears derived plans in authoring copies', function () {
    foreach ([32, 33] as $length) {
        $parser = new Choice(new Text(str_repeat('a', $length)), new Text(str_repeat('b', $length)));
        $grammar = $parser->freeze();
        $source = new ByteSource('c');
        $grammar->parseAll($source);
        $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
        $metrics = new ParseMetrics();
        $session = new ParseSession($source, metrics: $metrics);
        $root->recognizeInSession($source, 0, $session);
        expect($metrics->snapshot()['parser_entries'])->toBe($length === 32 ? 1 : 2)
            ->and($session->workUsed())->toBe($length === 32 ? 1 : 5);
        $dispatch = new ReflectionProperty($root, 'dispatch');
        expect(count($dispatch->getValue($root)))->toBe($length === 32 ? 0 : 1);
        $copy = (new GraphSnapshot())->copy($root);
        expect($dispatch->getValue($copy))->toBe([]);
    }
});

test('dispatch retains file offsets case and spacing scopes and exact budget failures', function () {
    $literal = str_repeat('A', 40);
    $choice = Choice::longest(new Text('Z' . $literal), new Text($literal));
    $parsers = [
        [$choice, $literal],
        [new CaseSensitive(false, $choice), strtolower($literal)],
        [(new Sequence(new Text('@'), $choice))->spacing(Repeat::star(new Text(' '))), '@ ' . $literal],
    ];
    foreach ($parsers as [$parser, $text]) {
        $stream = tmpfile();
        if ($stream === false) {
            throw new RuntimeException('Could not create choice fixture');
        }
        fwrite($stream, '>' . $text);
        $source = new Utf8FileSource($stream, 4);
        fclose($stream);
        try {
            $plain = $parser->freeze(FreezeFlags::NONE);
            $optimized = $parser->freeze();
            $limits = [null, new ParseLimits(maxDepth: 1), new ParseLimits(maxDepth: 2)];
            for ($work = 1; $work <= 50; ++$work) {
                $limits[] = new ParseLimits(maxWork: $work);
            }
            foreach ($limits as $limit) {
                $observations = [];
                foreach ([$plain, $optimized] as $grammar) {
                    try {
                        $result = $grammar->parseAll($source, 1, $limit);
                        $observations[] = [$result->success, $result->node, $result->diagnostic];
                    } catch (ParseLimitException $error) {
                        $observations[] = [$error->getMessage(), $error->byteOffset];
                    }
                }
                expect($observations[1])->toEqual($observations[0]);
            }
        } finally {
            $source->close();
        }
    }
});
