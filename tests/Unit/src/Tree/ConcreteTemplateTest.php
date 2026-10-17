<?php

declare(strict_types=1);

use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Tree\ConcreteTemplate;

test('concrete templates preserve every tree field and shift all spans onto another source', function () {
    $source = new ByteSource('#abc');
    $node = new ConcreteNode('outer', $source->span(1, 4), [
        new ConcreteNode('inner', $source->span(2, 3), [], true, 0, 'group', true, ['tag']),
    ], false, 'root');
    $template = ConcreteTemplate::from($node, 1);
    $destination = new ByteSource('prefixabc');
    $copy = $template->instantiate($destination, 6);
    expect($copy->name)->toBe('root')->and($copy->span->start)->toBe(6)->and($copy->rawText())->toBe('abc')
        ->and($copy->children[0]->span->start)->toBe(7)->and($copy->children[0]->name)->toBe(0)
        ->and($copy->children[0]->group)->toBe('group')->and($copy->children[0]->tags)->toBe(['tag'])
        ->and($copy->children[0]->isTrivia)->toBeTrue()->and($copy->children[0]->semanticLeaf)->toBeTrue();
});
