<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Directive\Lexeme;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimitException;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Utf8FileSource;
use Vanderlee\Comprehend\Source\Utf8Source;

test('composed scanners preserve trees diagnostics rollback and byte offsets', function (bool $utf8): void {
    $parser = new Sequence(
        new Text('['),
        new ReadWhileSet("a\u{e9}", 0, 2),
        new ReadWhileRange('0', '9', 2, 3),
        new Text(']'),
    );
    $plain = $parser->freeze(FreezeFlags::NONE);
    $prepared = $parser->freeze();
    foreach (['[12]', '[aa123]', "[\u{e9}12]", '[a1]', '[aa1234]', '[a?]', '[12]!', '[]'] as $input) {
        $source = $utf8 ? new Utf8Source('>' . $input) : new ByteSource('>' . $input);
        foreach (['matchPrefix', 'parseAll'] as $method) {
            for ($pass = 0; $pass < 2; ++$pass) {
                $expected = $plain->$method($source, 1);
                $actual = $prepared->$method($source, 1);
                expect([$actual->success, $actual->consumedByteLength, $actual->farthestFailure])
                    ->toBe([$expected->success, $expected->consumedByteLength, $expected->farthestFailure])
                    ->and($actual->node?->toArray())->toBe($expected->node?->toArray())
                    ->and($actual->ast()?->toArray())->toBe($expected->ast()?->toArray())
                    ->and($actual->diagnostic?->toArray())->toBe($expected->diagnostic?->toArray())
                    ->and($actual->value())->toBe($expected->value());
                if ($actual->success) {
                    expect($actual->rawText())->toBe($expected->rawText());
                }
            }
        }
    }
})->with([false, true]);

test('prepared scanner sequences reduce checked dispatch without removing syntax or work', function (): void {
    $parser = new Lexeme(new Sequence(new ReadWhileSet('a', 1, 1), new ReadWhileRange('0', '9')));
    $source = new Utf8Source('a123');
    $counts = [];
    $work = [];
    $trees = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $grammar = $parser->freeze($flags);
        $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
        $metrics = new ParseMetrics();
        $session = new ParseSession($source, metrics: $metrics);
        $result = new ParseResult($root->recognizeInSession($source, 0, $session));
        $counts[] = $metrics->snapshot()['parser_entries'];
        $work[] = $session->workUsed();
        $trees[] = $result->node->toArray();
    }
    expect($counts)->toBe([4, 2])->and($work[1])->toBe($work[0])->and($trees[1])->toBe($trees[0]);
});

test('scanner fusion respects spacing case overrides and cached effects around failed runs', function (): void {
    $observations = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $events = [];
        $first = (new Text('!'))->guard(static function () use (&$events): bool {
            $events[] = 'guard';

            return true;
        })->onCommit(static function () use (&$events): void {
            $events[] = 'first';
        });
        $scanner = (new ReadWhileSet('A', 1, 1))->caseInsensitive();
        $body = new Sequence($first, $scanner, new ReadWhileRange('0', '9', 2));
        $body->mapValue(static function (ParseResult $result) use (&$events): object {
            $events[] = 'value';

            return (object) ['text' => $result->rawText()];
        })->onCommit(static function () use (&$events): void {
            $events[] = 'body';
        });
        foreach ([false, true] as $lexeme) {
            $parser = new Space(new ReadWhileSet(' '), $lexeme ? new Lexeme($body) : $body);
            $grammar = $parser->freeze($flags);
            $events = [];
            $failed = $grammar->parse($lexeme ? '!a1' : '! a 1');
            expect($failed->success)->toBeFalse()->and($events)->toBe(['guard']);
            $events = [];
            $result = $grammar->parse($lexeme ? '!a12' : '! a 12');
            expect($result->success)->toBeTrue()->and($events)->toBe(['guard']);
            $value = $result->value();
            expect($result->value())->toBe($value)->and($events)->toBe(['guard', 'value']);
            $result->commit();
            $result->commit();
            $observations[$flags][$lexeme ? 1 : 0] = [$result->node->toArray(), $events];
        }
    }
    expect($observations[FreezeFlags::OPTIMIZE])->toBe($observations[FreezeFlags::NONE]);
});

test('composed scanner fusion preserves all exhausted work and depth limits', function (): void {
    $parser = new Lexeme(new Sequence(new ReadWhileSet('a', 1, 2), new ReadWhileRange('0', '9', 2, 3)));
    $budgets = [
        ...array_map(static fn(int $n): ParseLimits => new ParseLimits(maxWork: $n), range(1, 20)),
        ...array_map(static fn(int $n): ParseLimits => new ParseLimits(maxDepth: $n), range(1, 5)),
    ];
    foreach (['a123', 'a1', 'aa1234'] as $input) {
        foreach ($budgets as $limits) {
            $observations = [];
            foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
                try {
                    $result = $parser->freeze($flags)->parse($input, limits: $limits);
                    $observations[] = [$result->success, $result->node?->toArray(), $result->diagnostic?->toArray()];
                } catch (ParseLimitException $error) {
                    $observations[] = $error->getMessage();
                }
            }
            expect($observations[1])->toBe($observations[0]);
        }
    }
});

test('scanner metadata separates batches and abandoned choices never evaluate or commit it', function (): void {
    $observations = [];
    foreach ([FreezeFlags::NONE, FreezeFlags::OPTIMIZE] as $flags) {
        $events = [];
        $decorated = (new ReadWhileSet('x', 1, 1))->name('middle')->tag('token')
            ->mapValue(static function () use (&$events): string {
                $events[] = 'value';

                return 'mapped';
            })->onCommit(static function () use (&$events): void {
                $events[] = 'commit';
            });
        $sequence = new Sequence(
            new ReadWhileSet('a', 1, 1),
            new ReadWhileRange('0', '9', 1, 1),
            $decorated,
            new ReadWhileSet('b', 1, 1),
            new ReadWhileRange('0', '9', 2, 2),
        );
        $grammar = Choice::first($sequence, new Text('a1xb2'))->freeze($flags);
        $abandoned = $grammar->parse('a1xb2');
        expect($abandoned->success)->toBeTrue()->and($events)->toBe([]);
        $abandoned->value();
        $abandoned->commit();
        expect($events)->toBe([]);
        $selected = $grammar->parse('a1xb23');
        $selected->commit();
        $selected->commit();
        expect($events)->toBe(['commit']);
        $observations[] = [$selected->node->toArray(), $selected->ast()->toArray(), $events];
    }
    expect($observations[1])->toBe($observations[0]);
});

test('scanner batches bind fresh file spans and ordinary copies clear their prepared plans', function (): void {
    $grammar = (new Sequence(new ReadWhileSet("a\u{e9}", 1, 2), new ReadWhileRange('0', '9', 1)))->freeze();
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
    $copy = (new GraphSnapshot())->copy($root);
    foreach (["a12", "\u{e9}23"] as $input) {
        $stream = tmpfile();
        fwrite($stream, '>' . $input);
        $source = new Utf8FileSource($stream, pageSize: 4);
        try {
            $result = $grammar->parse($source, 1);
            $expected = $copy->parseAll($source, 1);
            expect($result->success)->toBeTrue()->and($result->rawText())->toBe($input)
                ->and($result->node)->toEqual($expected->node)
                ->and($result->node->children[0]->span->source)->toBe($source);
        } finally {
            $source->close();
            fclose($stream);
        }
    }
});

test('scanner exceptions restore lexeme and case scopes before session reuse', function (): void {
    $parser = new Lexeme(new Sequence(
        new ReadWhileSet('a', 1, 1),
        (new ReadWhileSet("\xff"))->caseInsensitive(),
    ));
    $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($parser->freeze());
    $source = new Utf8Source('ab');
    $session = new ParseSession($source);
    expect(fn() => $root->recognizeInSession($source, 0, $session))->toThrow(InvalidArgumentException::class);
    expect($session->isLexeme())->toBeFalse()->and($session->isCaseSensitive())->toBeTrue();
    expect((new Text('ab'))->recognizeInSession($source, 0, $session)->accepted)->toBeTrue();
});
