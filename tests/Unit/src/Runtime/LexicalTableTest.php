<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\LexicalTable;
use Vanderlee\Comprehend\Runtime\ParseMetrics;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

test('bounded lexical templates rebind trees and caller paths without retaining sources', function () {
    $parser = new Sequence(new Text('a'), new Set('bc'));
    $plan = LexicalTable::prepare($parser);
    expect($plan)->not->toBeNull();
    $source = new ByteSource('ab');
    $reference = WeakReference::create($source);
    $plan->attempt($source, 0, new ParseSession($source));
    unset($source);
    gc_collect_cycles();
    expect($reference->get())->toBeNull();
    $source = new ByteSource('#ab');
    $metrics = new ParseMetrics();
    $match = $plan->attempt($source, 1, new ParseSession($source, metrics: $metrics));
    expect($match->node)->toEqual($parser->matchPrefix($source, 1)->node)
        ->and($metrics->snapshot()['parser_entries'])->toBe(0);
});

test('bounded regex eligibility excludes input context and unbounded expressions', function (
    string $pattern,
    ?int $bound,
) {
    expect((new Regex($pattern))->lexicalLookahead())->toBe($bound);
})->with([
    ['/[\d]{2}/', 2], ['/[0-9A-Fa-f]{1,4}/A', 4], ['/[\x09\x20-\x7f]/', 1],
    ['/[0-9]+/', null], ['/(?<=a)[0-9]/', null], ['/[0-9]$/', null],
    ['/\G[0-9]/', null], ['/[0-9]/u', null], ['/[\p{L}]/u', null],
]);

test('lexical tables have a bounded capacity and saturation preserves recognition', function () {
    $parser = new Sequence(
        new Text('a'),
        new Set('bc'),
        new Set('bc'),
        new Set('bc'),
        new Set('bc'),
        new Set('bc'),
        new Set('bc'),
        new Set('bc'),
        new Set('bc'),
    );
    $plan = LexicalTable::prepare($parser);
    expect($plan)->not->toBeNull();
    for ($index = 0; $index < 256; ++$index) {
        $source = new ByteSource('a' . strtr(str_pad(decbin($index), 8, '0', STR_PAD_LEFT), '01', 'bc'));
        expect($plan->attempt($source, 0, new ParseSession($source))->node)->toEqual($parser->parseAll($source)->node);
    }
    expect(count((new ReflectionProperty(LexicalTable::class, 'entries'))->getValue($plan)))->toBe(128);
});
