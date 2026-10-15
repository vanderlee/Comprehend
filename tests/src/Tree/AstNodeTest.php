<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\AstNode;

final class AstNodeTest extends TestCase
{
    public function testNavigationUsesStrictNamesGroupsAndSourceOrder(): void
    {
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
    }

    public function testSurroundingSpansMustShareTheNodeSource(): void
    {
        $source = new ByteSource('a');
        $foreign = new ByteSource('a');
        $this->expectException(InvalidArgumentException::class);

        new AstNode('item', null, $source->span(0, 1), preamble: [$foreign->span(0, 1)]);
    }
}
