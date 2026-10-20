<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
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
