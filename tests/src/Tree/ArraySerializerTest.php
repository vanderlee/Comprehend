<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ArraySerializer;
use Vanderlee\Comprehend\Tree\ConcreteNode;

final class ArraySerializerTest extends TestCase
{
    public function testConcreteFieldsAndBinaryTextArePreserved(): void
    {
        $span = (new ByteSource("\xff"))->span(0, 1);
        $node = new ConcreteNode('text', $span, isTrivia: true, name: 0, semanticLeaf: true, tags: ['tag']);
        $this->assertSame([
            'name' => 0, 'group' => null, 'start' => 0, 'end' => 1,
            'text' => '/w==', 'textEncoding' => 'base64', 'children' => [],
            'kind' => 'text', 'trivia' => true, 'semanticLeaf' => true, 'tags' => ['tag'],
        ], $node->accept(new ArraySerializer()));
    }
}
