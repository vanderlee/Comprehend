<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(TestCase::class);

test('node retains unnamed syntax when projecting named ast', function (): void {
    $source = new ByteSource('(a)');
    $child = new ConcreteNode('text', $source->span(1, 2), name: 'item');
    $root = new ConcreteNode('sequence', $source->span(0, 3), [$child], tags: ['document']);

    $this->assertSame('(a)', $root->rawText());
    $this->assertSame([$child], $root->children);
    $this->assertTrue($root->hasTag('document'));
    $this->assertFalse($root->hasTag('missing'));
    $this->assertSame('item', $root->ast()->firstChild()->name);
    $this->assertSame('(a)', $root->ast()->firstChild()->surroundedText());
});

test('children from other sources are rejected', function (): void {
    $child = new ConcreteNode('text', (new ByteSource('a'))->span(0, 1));
    $this->expectException(InvalidArgumentException::class);

    new ConcreteNode('sequence', (new ByteSource('a'))->span(0, 1), [$child]);
});

test('leaf nodes still reject malformed tags', function (array $tags): void {
    $this->expectException(InvalidArgumentException::class);

    new ConcreteNode('text', (new ByteSource('x'))->span(0, 1), tags: $tags);
})->with([
    'empty tag' => [['']],
    'non-string tag' => [[3]],
    'associative tags' => [['label' => 'tag']],
]);
