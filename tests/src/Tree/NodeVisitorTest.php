<?php

declare(strict_types=1);

namespace Tests\Src\Tree;

use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Tree\NodeVisitor;

uses(TestCase::class);

test('nodes dispatch to their own visitor operation', function (): void {
    $span = (new ByteSource('a'))->span(0, 1);
    $concrete = new ConcreteNode('text', $span, name: 'letter');
    $ast = $concrete->ast();
    $visitor = $this->createMock(NodeVisitor::class);
    $visitor->expects($this->once())->method('visitConcrete')
        ->with($this->identicalTo($concrete))->willReturn('concrete');
    $visitor->expects($this->once())->method('visitAst')->with($this->identicalTo($ast))->willReturn('ast');
    $visitor->expects($this->never())->method('visitData');
    $this->assertSame('concrete', $concrete->accept($visitor));
    $this->assertSame('ast', $ast->accept($visitor));
});
