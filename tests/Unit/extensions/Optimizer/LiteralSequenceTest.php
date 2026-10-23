<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Extensions\Optimizer\LiteralSequence;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;

test('literal batches instantiate original operand syntax without execution records', function (): void {
    $first = (new Text('ab'))->name('first')->tag('literal');
    $second = Repeat::exact((new Text('c'))->name('letter'), 2)->name('repeated');
    $sequence = new Sequence($first, $second);
    $batch = LiteralSequence::prepare($sequence)[0];
    $source = new ByteSource('>abcc!');
    $session = new ParseSession($source);
    $session->recordFailure(5, 'inherited', [['caller']]);
    $before = $session->diagnostic();
    $nodes = $batch->attempt($source, 1, $session);

    expect($nodes)->toHaveCount(2)->and($session->diagnostic())->toBe($before);
    foreach ($nodes as $node) {
        expect($node)->toBeInstanceOf(ConcreteNode::class);
    }
    $expected = $sequence->matchPrefix($source, 1)->node->children;
    expect($nodes)->toEqual($expected)->and($nodes[0]->span->source)->toBe($source)
        ->and($nodes[0]->tags)->toBe(['literal'])->and($nodes[1]->children)->toHaveCount(2);
    $again = $batch->attempt($source, 1, new ParseSession($source));
    expect($again)->toEqual($nodes)->and($again[0])->not->toBe($nodes[0]);
});

test('literal batches retain failure fallback and leave limited recognition untouched', function (): void {
    $batch = LiteralSequence::prepare(new Sequence(new Text('ab'), new Text('cd')))[0];
    $source = new ByteSource('ab?');
    $session = new ParseSession($source);
    expect($batch->attempt($source, 0, $session))->toBe(2)
        ->and($session->diagnostic()->byteOffset)->toBe(2)
        ->and($session->diagnostic()->expected)->toBe(['"cd"']);
    foreach ([new ParseLimits(maxWork: 1), new ParseLimits(maxDepth: 1)] as $limits) {
        $session = new ParseSession($source, limits: $limits);
        expect($batch->attempt($source, 0, $session))->toBeNull()->and($session->workUsed())->toBe(0);
    }
});
