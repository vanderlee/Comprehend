<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\AstNode;
use Vanderlee\Comprehend\Tree\ConcreteNode;

uses(TestCase::class);

test('navigation uses strict names groups and source order', function (): void {
    $source = new ByteSource('ab');
    $first = new AstNode(1, 'left', $source->span(0, 1), tags: ['letter']);
    $last = new AstNode('1', 'right', $source->span(1, 2));
    $root = new AstNode(null, null, $source->span(0, 2), [$first, $last]);

    $this->assertSame($first, $root->firstChild());
    $this->assertSame($last, $root->lastChild());
    $this->assertSame($first, $root->firstNamed(1));
    $this->assertSame([$last], $root->allNamed('1', 'right'));
    $this->assertSame($first, $root->firstTagged('letter'));
    $this->assertSame([$first], $root->allInGroup('left'));
    $this->assertSame([$first, $last], iterator_to_array($root->descendants()));
    $this->assertNull($root->child(2));
    $this->assertNull($root->firstNamed('missing'));
});

test('surrounding spans must share the node source', function (): void {
    $source = new ByteSource('a');
    $foreign = new ByteSource('a');
    $this->expectException(InvalidArgumentException::class);

    new AstNode('item', null, $source->span(0, 1), preamble: [$foreign->span(0, 1)]);
});

test('overlapping children do not assign already covered bytes to later gaps', function (): void {
    $source = new ByteSource('(abcd)');
    $root = new ConcreteNode('intersection', $source->span(0, 6), [
        new ConcreteNode('text', $source->span(1, 4), name: 'wide'),
        new ConcreteNode('text', $source->span(2, 3), name: 'overlap'),
        new ConcreteNode('empty', $source->span(5, 5), name: 'empty'),
    ], name: 'root');

    $ast = $root->ast();
    $this->assertSame('(abc', $ast->firstNamed('wide')->surroundedText());
    $this->assertSame('b', $ast->firstNamed('overlap')->surroundedText());
    $empty = $ast->firstNamed('empty');
    $this->assertSame('d', $empty->preambleText());
    $this->assertSame(')', $empty->postambleText());
    $this->assertSame([4, 5], [$empty->preamble[0]->start, $empty->preamble[0]->end]);
    $this->assertSame([5, 6], [$empty->postamble[0]->start, $empty->postamble[0]->end]);
});
