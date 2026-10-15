<?php

declare(strict_types=1);

namespace Tests\features\trees;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Comprehend as C;

uses(TestCase::class);

test('navigation uses immediate children strict names groups and source order', function (): void {
    $ast = C::sequence(
        C::text('a')->named('item', 'left'),
        C::sequence('(', C::text('n')->named('item', 'nested'), ')')->named('box', 'nested'),
        C::text('b')->named('item', 'right'),
        C::text('c')->named(0),
    )->parse('a(n)bc')->ast();
    $this->assertSame($ast->children[0], $ast->firstChild());
    $this->assertSame($ast->children[3], $ast->lastChild());
    $this->assertSame($ast->children[1], $ast->child(1));
    $this->assertNull($ast->child(-1));
    $this->assertNull($ast->child(4));
    $this->assertSame($ast->children[0], $ast->firstNamed('item'));
    $this->assertSame($ast->children[2], $ast->firstNamed('item', 'right'));
    $this->assertSame([$ast->children[0], $ast->children[2]], $ast->allNamed('item'));
    $this->assertSame([$ast->children[2]], $ast->allNamed('item', 'right'));
    $this->assertSame($ast->children[3], $ast->firstNamed(0));
    $this->assertNull($ast->firstNamed('0'));
    $this->assertNull($ast->firstNamed('item', 'nested'));
    $this->assertSame([], $ast->allNamed('missing'));
    $this->assertSame($ast->children[1], $ast->firstInGroup('nested'));
    $this->assertSame([$ast->children[1]], $ast->allInGroup('nested'));
    $this->assertSame($ast->children[3], $ast->firstInGroup(null));
    $this->assertNull($ast->firstInGroup('missing'));
    $descendants = iterator_to_array($ast->descendants(), false);
    $this->assertSame(
        ['a', '(n)', 'n', 'b', 'c'],
        array_map(static fn($node): string => $node->rawText(), $descendants),
    );
    $this->assertSame($ast->children[1]->children[0], $descendants[2]);
});

test('empty navigation and reads do not evaluate semantic values', function (): void {
    $reads = 0;
    $result = C::text('a')->named('leaf')->mapValue(static function () use (&$reads): int {
        ++$reads;

        return 1;
    })->parse('a');
    $ast = $result->ast();
    $this->assertNull($ast->firstChild());
    $this->assertNull($ast->lastChild());
    $this->assertNull($ast->firstNamed('leaf'));
    $this->assertSame([], $ast->allNamed('leaf'));
    $this->assertSame([], $ast->allInGroup(null));
    $this->assertSame([], iterator_to_array($ast->descendants()));
    $this->assertSame(0, $reads);
    $this->assertSame('a', $ast->rawText());
});
