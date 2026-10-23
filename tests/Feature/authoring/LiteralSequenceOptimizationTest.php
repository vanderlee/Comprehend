<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('compiled literal sequences preserve named nested trees diagnostics and source offsets', function (): void {
    $rules = new Ruleset(['pair' => C::sequence('a', 'b')->named('pair')]);
    $unit = new Sequence($rules->ref('pair'), Repeat::exact(new Text('cd'), 2)->name('repeated'));
    $parser = Choice::first(new Sequence(new Text('!'), new Text('no')), $unit);
    $plain = $parser->freeze(FreezeFlags::NONE);
    $optimized = $parser->freeze();
    foreach (['abcdcd', 'abcdcd!', 'abcXcd', 'abcdc', '', 'abé'] as $text) {
        foreach ([new ByteSource('>' . $text), new Utf8Source('>' . $text)] as $source) {
            for ($pass = 0; $pass < 2; ++$pass) {
                foreach (['parseAll', 'matchPrefix'] as $operation) {
                    $expected = $plain->$operation($source, 1);
                    $result = $optimized->$operation($source, 1);
                    expect($result->success)->toBe($expected->success)
                        ->and($result->consumedByteLength)->toBe($expected->consumedByteLength)
                        ->and($result->node)->toEqual($expected->node)
                        ->and($result->diagnostic)->toEqual($expected->diagnostic)
                        ->and($result->ast())->toEqual($expected->ast())
                        ->and($result->value())->toBe($expected->value());
                }
            }
        }
    }
});

test('literal batching reduces parser entries while preserving every character node', function (): void {
    $parser = new Sequence(Repeat::exact(new Text('a'), 128), new Text('!'));
    $source = new ByteSource(str_repeat('a', 128) . '!');
    $counts = [];
    $nodes = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $grammar = $parser->freeze($flags);
        $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
        $metrics = new ParseMetrics();
        $match = $root->recognizeInSession($source, 0, new ParseSession($source, metrics: $metrics));
        $counts[] = $metrics->snapshot()['parser_entries'];
        $nodes[] = $match->node;
    }
    expect($nodes[1])->toEqual($nodes[0])
        ->and($nodes[1]->children[0]->children)->toHaveCount(128)
        ->and($counts[1])->toBeLessThan($counts[0] / 10);
});

test('literal batching retains outer mappings guards validation and explicit effects', function (): void {
    $events = [];
    $parser = (new Sequence(new Text('a'), new Text('b')))
        ->guard(static function () use (&$events): bool {
            $events[] = 'guard';

            return true;
        })->mapValue(static function () use (&$events): int {
            $events[] = 'value';

            return 7;
        })->validateSemantic(static function () use (&$events): bool {
            $events[] = 'validate';

            return true;
        })->onCommit(static function () use (&$events): void {
            $events[] = 'commit';
        });
    $grammar = $parser->freeze();
    expect($events)->toBe([]);
    $result = $grammar->parse('ab');
    expect($events)->toBe(['guard'])->and($result->value())->toBe(7)->and($result->value())->toBe(7);
    $result->commit();
    $result->commit();
    expect($events)->toBe(['guard', 'value', 'validate', 'commit']);
});

test('literal compilation never executes child callbacks while freezing', function (): void {
    $calls = 0;
    $child = (new Text('a'))->guard(static function () use (&$calls): bool {
        ++$calls;

        return true;
    });
    $grammar = (new Sequence(new Text('!'), new Sequence($child, new Text('b'))))->freeze();
    expect($calls)->toBe(0)->and($grammar->parse('!ab')->success)->toBeTrue()->and($calls)->toBe(1);
});

test('literal sequences retain ordinary case spacing unicode and custom parser behavior', function (
    Parser $parser,
    string $text,
): void {
    $source = new Utf8Source($text);
    $expected = $parser->freeze(FreezeFlags::NONE)->parse($source);
    $actual = $parser->freeze()->parse($source);
    expect($actual->success)->toBe($expected->success)->and($actual->node)->toEqual($expected->node)
        ->and($actual->diagnostic)->toEqual($expected->diagnostic);
})->with([
    [new CaseSensitive(false, new Sequence(new Text('A'), new Text('b'))), 'aB'],
    [new Sequence((new Text('A'))->caseInsensitive(), new Text('b')), 'ab'],
    [(new Sequence(new Text('a'), new Text('b')))->spacing(new Text(' ')), 'a b'],
    [new Sequence(new Text('é'), new Text('b')), 'éb'],
    [new Sequence(new class ('a') extends Text {
        // Custom subclasses must retain their ordinary recognition boundary.
    }, new Text('b')), 'ab'],
    [new Sequence(Repeat::exact(new Text('a'), 0), new Text('b')), 'b'],
    [new Sequence(Repeat::plus(new Text('a')), new Text('b')), 'aab'],
]);

test('literal sequences preserve precise work and depth limit failures', function (): void {
    $parser = new Sequence(Repeat::exact(new Text('a'), 10), new Text('b'));
    foreach ([new ParseLimits(maxWork: 7), new ParseLimits(maxDepth: 2)] as $limits) {
        $exceptions = [];
        foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
            try {
                $parser->freeze($flags)->parse('aaaaaaaaaab', limits: $limits);
            } catch (ParseLimitException $exception) {
                $exceptions[] = [$exception->getMessage(), $exception->byteOffset];
            }
        }
        expect($exceptions)->toHaveCount(2)->and($exceptions[1])->toBe($exceptions[0]);
    }
});

test('compiled literal recipes bind to each file source rather than the classification source', function (): void {
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, '>abcd');
    rewind($stream);
    $source = new Utf8FileSource($stream);
    try {
        $parser = new Sequence(new Text('ab'), new Text('cd'));
        $result = $parser->freeze()->parse($source, 1);
        expect($result->node)->toEqual($parser->freeze(FreezeFlags::NONE)->parse($source, 1)->node)
            ->and($result->node->children[0]->span->source)->toBe($source)
            ->and($result->rawText())->toBe('abcd');
    } finally {
        $source->close();
        fclose($stream);
    }
});

test('mixed literal runs preserve static metadata regex values diagnostics and byte offsets', function (): void {
    $parser = new Sequence(
        (new Text('a'))->name('first')->tag('letter'),
        new Text('b'),
        (new Regex('/[0-9]+/A'))->pushResult('number', static fn(string $text): int => (int) $text),
        new Text('c'),
        (new Text('d'))->name('last'),
    );
    $plain = $parser->freeze(FreezeFlags::NONE);
    $optimized = $parser->freeze();
    foreach (['ab12cd', 'ab12cd!', 'ab12c?', 'ab12c', 'a?12cd', 'ab?cd'] as $text) {
        foreach ([new ByteSource("\xc3\xa9" . $text), new Utf8Source("\u{e9}" . $text)] as $source) {
            foreach (['parseAll', 'matchPrefix'] as $operation) {
                $expected = $plain->$operation($source, 2);
                $actual = $optimized->$operation($source, 2);
                expect($actual->success)->toBe($expected->success)
                    ->and($actual->consumedByteLength)->toBe($expected->consumedByteLength)
                    ->and($actual->node)->toEqual($expected->node)
                    ->and($actual->ast())->toEqual($expected->ast())
                    ->and($actual->diagnostic)->toEqual($expected->diagnostic)
                    ->and($actual->results())->toBe($expected->results())
                    ->and($actual->value())->toBe($expected->value());
            }
        }
    }
});

test('mixed literal fallback executes preceding guards and dynamic names once and keeps effects deferred', function (
    int $flags,
): void {
    $events = [];
    $middle = (new Regex('/[0-9]+/A'))
        ->name(static function () use (&$events): string {
            $events[] = 'name';
            return 'number';
        })->mapValue(static function (ParseResult $result) use (&$events): int {
            $events[] = 'value';
            return (int) $result->rawText();
        })->validateSemantic(static function (ParseResult $result) use (&$events): bool {
            $events[] = 'validate';
            return $result->value() === 12;
        })->onCommit(static function () use (&$events): void {
            $events[] = 'commit';
        });
    $parser = new Sequence(
        (new Text('@'))->guard(static function () use (&$events): bool {
            $events[] = 'guard';
            return true;
        }),
        new Text('a'),
        new Text('b'),
        $middle,
        new Text('c'),
        new Text('d'),
    );
    $grammar = $parser->freeze($flags);
    expect($events)->toBe([]);
    expect($grammar->parse('@ab12c?')->success)->toBeFalse()->and($events)->toBe(['guard', 'name']);
    $events = [];
    $result = $grammar->parse('@ab12cd');
    expect($result->success)->toBeTrue()->and($events)->toBe(['guard', 'name']);
    $result->commit();
    $result->commit();
    expect($events)->toBe(['guard', 'name', 'validate', 'value', 'commit']);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('literal runs on both sides of a regex reduce dispatch without adding tree boundaries', function (): void {
    $left = array_fill(0, 32, new Text('a'));
    $right = array_fill(0, 32, new Text('b'));
    $parser = new Sequence(...[...$left, new Regex('/[0-9]+/A'), ...$right]);
    $source = new ByteSource(str_repeat('a', 32) . '12' . str_repeat('b', 32));
    $observations = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $grammar = $parser->freeze($flags);
        $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
        $metrics = new ParseMetrics();
        $match = $root->recognizeInSession($source, 0, new ParseSession($source, metrics: $metrics));
        $observations[] = [$match->node, $metrics->snapshot()['parser_entries']];
    }
    expect($observations[1][0])->toEqual($observations[0][0])
        ->and($observations[1][0]->children)->toHaveCount(65)
        ->and($observations[1][1])->toBeLessThan($observations[0][1] / 10);
});

test('mixed sequences retain inherited spacing case policies and exact exhausted budgets', function (): void {
    $sequence = static fn(): Sequence => new Sequence(
        new Text('A'),
        new Text('B'),
        new Regex('/[0-9]+/A'),
        new Text('C'),
        new Text('D'),
    );
    $parsers = [
        [$sequence(), 'AB12CD'],
        [new CaseSensitive(false, $sequence()), 'ab12cd'],
        [(new Sequence($sequence()))->spacing(Repeat::star(new Text(' '))), 'A B 12 C D'],
    ];
    foreach ($parsers as [$parser, $text]) {
        $source = new Utf8Source($text);
        foreach ([null, new ParseLimits(maxWork: 12), new ParseLimits(maxDepth: 2)] as $limits) {
            $observations = [];
            foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
                try {
                    $result = $parser->freeze($flags)->parse($source, limits: $limits);
                    $observations[] = [$result->success, $result->node, $result->diagnostic];
                } catch (ParseLimitException $error) {
                    $observations[] = [$error->getMessage(), $error->byteOffset];
                }
            }
            expect($observations[1])->toEqual($observations[0]);
        }
    }
});

test('literal run limits split oversized sequences without changing their original syntax', function (): void {
    $parsers = [
        new Sequence(
            new Text(str_repeat('a', 40000)),
            new Text(str_repeat('b', 40000)),
            new Text('c'),
            new Text('d'),
        ),
        new Sequence(
            Repeat::exact(new Text('a'), 3000),
            Repeat::exact(new Text('b'), 3000),
            new Text('c'),
            new Text('d'),
        ),
    ];
    $inputs = [
        str_repeat('a', 40000) . str_repeat('b', 40000) . 'cd',
        str_repeat('a', 3000) . str_repeat('b', 3000) . 'cd',
    ];
    foreach ($parsers as $index => $parser) {
        $source = new ByteSource($inputs[$index]);
        $expected = $parser->freeze(FreezeFlags::NONE)->parse($source);
        $actual = $parser->freeze()->parse($source);
        expect($actual->success)->toBeTrue()->and($actual->node)->toEqual($expected->node)
            ->and($actual->diagnostic)->toEqual($expected->diagnostic);
    }
});

test('literal nodes preserve lifecycle order around scanner and executable operands', function (int $flags): void {
    $events = [];
    $marker = (new Regex('/Z/A'))->setResult('marker', static function (string $text) use (&$events): string {
        $events[] = 'collection';
        return $text;
    })->mapValue(static function (ParseResult $result) use (&$events): string {
        $events[] = 'child-value';
        return $result->rawText();
    })->validateSemantic(static function (ParseResult $result) use (&$events): bool {
        $events[] = 'validate';
        return $result->value() === 'Z';
    })->onCommit(static function () use (&$events): void {
        $events[] = 'child-effect';
    });
    $parser = (new Sequence(
        (new Nothing())->guard(static function () use (&$events): bool {
            $events[] = 'guard';
            return true;
        }),
        (new Text('a'))->name('letter'),
        new Text('b'),
        new ReadWhileSet('x', 0, 1),
        new ReadWhileRange('0', '9', 1, 3),
        $marker,
        new Text('c'),
        new Text('d'),
    ))->mapValue(static function (ParseResult $result) use (&$events): array {
        $events[] = 'root-value';
        return [$result->rawText(), $result->result('marker')];
    })->onCommit(static function () use (&$events): void {
        $events[] = 'root-effect';
    });
    $grammar = $parser->freeze($flags);
    expect($events)->toBe([])->and($grammar->parse('abx12Zc?')->success)->toBeFalse()
        ->and($events)->toBe(['guard']);
    foreach (['abx12Zcd', 'ab12Zcd'] as $text) {
        $events = [];
        $result = $grammar->parseAll(new Utf8Source('>' . $text), 1);
        expect($result->success)->toBeTrue()->and($events)->toBe(['guard'])
            ->and($result->node->children)->toHaveCount(8)
            ->and($result->node->children[1]->name)->toBe('letter')
            ->and($result->node->children[3]->rawText())->toBe(str_contains($text, 'x') ? 'x' : '');
        expect($result->value())->toBe([$text, 'Z'])->and($result->value())->toBe([$text, 'Z']);
        $result->commit();
        $result->commit();
        expect($events)->toBe(['guard', 'root-value', 'collection', 'validate',
            'child-value', 'child-effect', 'root-effect']);
    }
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('full width literal syntax retains its shared immutable parent span', function (bool $emptySuffix): void {
    $operands = [Repeat::exact((new Text('ab'))->name('pair'), 2)->name('block')];
    if ($emptySuffix) {
        $operands[] = Repeat::exact(new Text('z'), 0)->name('empty');
    }
    $parser = new Sequence(...$operands);
    $result = $parser->freeze()->parseAll(new Utf8Source('>abab'), 1);
    expect($result->success)->toBeTrue()->and($result->node->span)->toBe($result->node->children[0]->span)
        ->and($result->node->children[0]->name)->toBe('block')
        ->and($result->node->children[0]->children)->toHaveCount(2);
})->with([false, true]);
