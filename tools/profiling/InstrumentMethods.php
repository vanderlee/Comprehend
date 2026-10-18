<?php

declare(strict_types=1);

namespace ComprehendProfiling;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Finally_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/** Wraps source copies only; try/finally retains returns, exceptions and scope restoration. */
final class InstrumentMethods extends NodeVisitorAbstract
{
    private int $methodDepth = 0;

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof ClassMethod) {
            ++$this->methodDepth;
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Node\Expr\New_ && $this->methodDepth > 0) {
            return new StaticCall(new FullyQualified(MethodTrace::class), 'allocated', [new Node\Arg($node)]);
        }
        if ($node instanceof ClassMethod) {
            --$this->methodDepth;
        }
        if (!$node instanceof ClassMethod || $node->stmts === null) {
            return null;
        }
        $finder = new NodeFinder();
        if (
            $finder->findFirstInstanceOf($node->stmts, Node\Expr\Yield_::class) !== null
            || $finder->findFirstInstanceOf($node->stmts, Node\Expr\YieldFrom::class) !== null
        ) {
            return null;
        }
        $node->stmts = [
            new Expression(new StaticCall(new FullyQualified(MethodTrace::class), 'enter', [
                new Node\Arg(new Node\Scalar\MagicConst\Method()),
            ])),
            new TryCatch($node->stmts, [], new Finally_([
                new Expression(new StaticCall(new FullyQualified(MethodTrace::class), 'leave')),
            ])),
        ];

        return $node;
    }
}
