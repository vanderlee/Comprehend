<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;

final class ConcreteNodeTest extends TestCase
{
    public function testNodeRetainsUnnamedSyntaxWhenProjectingNamedAst(): void
    {
        $source = new ByteSource('(a)');
        $child = new ConcreteNode('text', $source->span(1, 2), name: 'item');
        $root = new ConcreteNode('sequence', $source->span(0, 3), [$child], tags: ['document']);

        $this->assertSame('(a)', $root->rawText());
        $this->assertSame([$child], $root->children);
        $this->assertTrue($root->hasTag('document'));
        $this->assertFalse($root->hasTag('missing'));
        $this->assertSame('item', $root->ast()->firstChild()->name);
        $this->assertSame('(a)', $root->ast()->firstChild()->surroundedText());
    }

    public function testChildrenFromOtherSourcesAreRejected(): void
    {
        $child = new ConcreteNode('text', (new ByteSource('a'))->span(0, 1));
        $this->expectException(InvalidArgumentException::class);

        new ConcreteNode('sequence', (new ByteSource('a'))->span(0, 1), [$child]);
    }
}
