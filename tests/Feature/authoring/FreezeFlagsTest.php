<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\FreezeFlags;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

test('all freeze entry points honor flags and default to optimization', function (string $entry) {
    $parser = Repeat::star(new Set('ab'))->name('letters')->tag('run');
    $prepare = match ($entry) {
        'parser' => $parser->freeze(...),
        'facade' => C::of($parser)->freeze(...),
        'factory parser' => static fn(int $flags = FreezeFlags::DEFAULT) => FrozenGrammar::from($parser, $flags),
        'factory facade' => static fn(int $flags = FreezeFlags::DEFAULT) => FrozenGrammar::from(C::of($parser), $flags),
    };
    $source = new ByteSource(str_repeat('ab', 20));
    $counts = [];
    foreach ([$prepare(), $prepare(FreezeFlags::OPTIMIZE), $prepare(FreezeFlags::NONE)] as $grammar) {
        // Profile the prepared runtime graph without adding a production introspection API.
        $root = (new ReflectionProperty(FrozenGrammar::class, 'root'))->getValue($grammar);
        $metrics = new ParseMetrics();
        $result = $root->recognizeInSession($source, 0, new ParseSession($source, metrics: $metrics));
        expect($result->node)->toEqual($parser->parseAll($source)->node);
        $counts[] = $metrics->snapshot()['parser_entries'];
    }
    expect($counts[0])->toBe($counts[1])->and($counts[0])->toBeLessThan($counts[2]);
})->with(['parser', 'facade', 'factory parser', 'factory facade']);

test('facade caching separates flag sets in either preparation order', function (int $firstFlags) {
    $facade = C::set('ab')->many();
    $secondFlags = $firstFlags ^ FreezeFlags::OPTIMIZE;
    $first = $facade->freeze($firstFlags);
    $second = $facade->freeze($secondFlags);
    expect($second)->not->toBe($first)
        ->and($facade->freeze($firstFlags))->toBe($first)
        ->and($facade->freeze($secondFlags))->toBe($second)
        ->and($facade->freeze())->toBe($facade->freeze(FreezeFlags::OPTIMIZE))
        ->and($facade->parse('abba')->success)->toBeTrue();
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('disabling optimization still resolves rules and isolates snapshots without caching deferred graphs', function () {
    $rules = new Ruleset(['letters' => C::set('ab')->many()]);
    $facade = $rules->rule('letters');
    $first = $facade->freeze(FreezeFlags::NONE);
    expect($facade->freeze(FreezeFlags::NONE))->not->toBe($first);
    $rules->define('letters', C::text('new'));
    expect($first->parse('abba')->success)->toBeTrue()
        ->and($facade->freeze(FreezeFlags::NONE)->parse('new')->success)->toBeTrue();
});

test('all flag sets keep nullable repetition safety checks', function (int $flags) {
    expect(static fn() => Repeat::star(new Nothing())->freeze($flags))->toThrow(LogicException::class);
})->with([FreezeFlags::NONE, FreezeFlags::OPTIMIZE]);

test('invalid flags are rejected before resolving factories or returning cached snapshots', function () {
    $calls = 0;
    $facade = C::of(new Definition(static function () use (&$calls) {
        ++$calls;
        return new Set('ab');
    }));
    $cached = C::set('ab')->many();
    $cached->freeze();
    $preparations = [
        static fn() => $facade->freeze(2),
        static fn() => $facade->parser()->freeze(2),
        static fn() => FrozenGrammar::from($facade, 2),
        static fn() => $cached->freeze(2),
    ];
    foreach ($preparations as $prepare) {
        expect($prepare)->toThrow(InvalidArgumentException::class, 'Unknown freeze flags');
    }
    expect($calls)->toBe(0);
});
