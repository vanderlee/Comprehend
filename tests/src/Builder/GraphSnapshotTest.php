<?php

declare(strict_types=1);

namespace Tests\Src\Builder;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;

final class GraphSnapshotTest extends TestCase
{
    public function testCopiesDetachOwnedChildrenButPreserveSharing(): void
    {
        $child = new Text('a');
        $original = new Sequence($child, $child);
        $copy = (new GraphSnapshot())->copy($original);
        $children = iterator_to_array($copy);
        $child->guard(static fn(): bool => false);

        $this->assertNotSame($original, $copy);
        $this->assertNotSame($child, $children[0]);
        $this->assertSame($children[0], $children[1]);
        $this->assertTrue($copy->parseAll(new ByteSource('aa'))->success);
        $this->assertFalse($original->parseAll(new ByteSource('aa'))->success);
    }
}
