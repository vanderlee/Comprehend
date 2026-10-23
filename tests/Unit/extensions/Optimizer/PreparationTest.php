<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Extensions\Optimizer\Preparation;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;

test('cached classifications retain their depth bounds in deeper callers', function (string $method, int $limit): void {
    $preparation = new Preparation();
    $shared = new Sequence(new Text('a'));
    expect($preparation->$method($shared))->not->toBeNull();
    $root = $shared;
    for ($i = 0; $i < $limit - 2; ++$i) {
        $root = new Sequence($root);
    }
    expect($preparation->$method($root))->not->toBeNull()
        ->toBe((new Preparation())->$method($root));
    $tooDeep = new Sequence($root);
    expect($preparation->$method($tooDeep))->toBeNull()
        ->toBe((new Preparation())->$method($tooDeep));
})->with([['literal', 32], ['lookahead', 17]]);

test('failed deep classifications do not poison shallower uses', function (string $method, int $limit): void {
    $shared = new Sequence(new Text('a'));
    $root = $shared;
    for ($i = 0; $i < $limit; ++$i) {
        $root = new Sequence($root);
    }
    $preparation = new Preparation();
    expect($preparation->$method($root))->toBeNull()
        ->and($preparation->$method($shared))->toBe((new Preparation())->$method($shared));
})->with([['literal', 32], ['lookahead', 17]]);

test('shared literal descriptors still charge every expanded occurrence', function (): void {
    $preparation = new Preparation();
    $text = new Text(str_repeat('a', 32768));
    expect($preparation->literal($text))->not->toBeNull()
        ->and($preparation->literal(new Sequence($text, $text)))->not->toBeNull()
        ->and($preparation->literal(new Sequence($text, $text, $text)))->toBeNull();
    $unit = new Text('a');
    expect($preparation->literal(Repeat::exact($unit, 4095)))->not->toBeNull()
        ->and($preparation->literal(Repeat::exact($unit, 4096)))->toBeNull();
});

test('recursive productions remain ineligible after successful sibling classification', function (): void {
    $rules = new Ruleset();
    $text = new Text('a');
    $rules->define('word', new Sequence(new Sequence($text), Repeat::optional($rules->ref('word'))));
    $root = (new GraphSnapshot())->copy($rules->ref('word'));
    $prefix = $root->children()[0]->children()[0];
    $preparation = new Preparation();
    foreach (['literal', 'lookahead'] as $method) {
        expect($preparation->$method($prefix))->not->toBeNull()
            ->and($preparation->$method($root))->toBeNull();
    }
});

test('shared literal templates rebind offsets and retain no recognition state', function (): void {
    $parser = new Sequence(new Text('a'), new Text('b'));
    $preparation = new Preparation();
    $source = new ByteSource('#abab');
    $session = new ParseSession($source);
    $sourceReference = WeakReference::create($source);
    $sessionReference = WeakReference::create($session);
    $first = $preparation->literalTemplate($parser, $source, 1, $session);
    $second = $preparation->literalTemplate($parser, $source, 3, $session);
    expect($first[0])->toBe(2)->and($second[1])->toBe($first[1])
        ->and($second[1]->instantiate($source, 3))->toEqual($parser->matchPrefix($source, 3)->node);
    unset($source, $session);
    gc_collect_cycles();
    expect($sourceReference->get())->toBeNull()->and($sessionReference->get())->toBeNull();
    $parserReference = WeakReference::create($parser);
    unset($parser);
    expect($parserReference->get())->toBeNull();
});
