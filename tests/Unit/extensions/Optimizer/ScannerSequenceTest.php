<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileRange;
use Vanderlee\Comprehend\Extensions\Optimizer\Parser\ReadWhileSet;
use Vanderlee\Comprehend\Extensions\Optimizer\ScannerSequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;

test('scanner runs stop at metadata and retain isolated operands', function (): void {
    $first = new ReadWhileSet('a', 1, 1);
    $second = new ReadWhileRange('0', '9');
    $boundary = (new ReadWhileSet('b'))->name('boundary');
    $last = new ReadWhileSet('c');
    $steps = ScannerSequence::prepare([$first, $second, $boundary, $last, new Text('!')]);

    expect($steps)->toHaveCount(4)
        ->and($steps[0])->toBeInstanceOf(ScannerSequence::class)
        ->and($steps[0]->parsers())->toBe([$first, $second])
        ->and($steps[1])->toBe($boundary)
        ->and($steps[2])->toBe($last);
});

test('scanner batches retain independent zero width and consuming child syntax', function (): void {
    $steps = ScannerSequence::prepare([new ReadWhileSet('a'), new ReadWhileRange('0', '9', 1, 2)]);
    $source = new ByteSource('12!');
    $session = new ParseSession($source);
    $children = $steps[0]->attempt($source, 0, $session);

    expect($children)->toHaveCount(2)
        ->and($children[0])->toBeInstanceOf(ConcreteNode::class)
        ->and($children[1])->toBeInstanceOf(ConcreteNode::class)
        ->and($children[0]->span->start)->toBe(0)
        ->and($children[0]->span->end)->toBe(0)
        ->and($children[1]->span->end)->toBe(2)
        ->and($children[0])->not->toBe($children[1])
        ->and($children[1]->rawText())->toBe('12');
});
