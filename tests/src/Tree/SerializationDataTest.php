<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Tree\SerializationData;

uses(TestCase::class);

test('shared data keeps original span and child identity', function (): void {
    $span = (new ByteSource('a'))->span(0, 1);
    $child = new ConcreteNode('text', $span);
    $node = new ConcreteNode('sequence', $span, [$child], name: 0, group: 'g', tags: ['tag']);
    $reader = new class {
        use SerializationData;

        public function read(ConcreteNode $node): array
        {
            return self::data($node);
        }
    };
    $this->assertSame([
        'name' => 0, 'group' => 'g', 'span' => $span,
        'children' => [$child], 'tags' => ['tag'],
    ], $reader->read($node));
});
