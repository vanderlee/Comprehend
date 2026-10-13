<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use InvalidArgumentException;
use Vanderlee\Comprehend\Core\Token;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Source\Span;

/** Canonical byte coordinates; outputs retain the existing lazy match projections. */
final class ParseResult
{
    public readonly bool $success;
    public readonly Span $span;
    public readonly int $start;
    public readonly int $end;
    public readonly int $consumedByteLength;
    public readonly ?int $farthestFailure;
    public readonly ?Diagnostic $diagnostic;
    public readonly ?\Vanderlee\Comprehend\Tree\ConcreteNode $node;
    private ?\Vanderlee\Comprehend\Tree\AstNode $ast = null;

    public function ast(): ?\Vanderlee\Comprehend\Tree\AstNode
    {
        return $this->node === null ? null : ($this->ast ??= $this->node->ast());
    }

    public function __construct(private readonly AbstractMatch $match)
    {
        $span = $match->span;
        if ($span === null) {
            throw new InvalidArgumentException('ParseResult requires a source-bound match; reparse legacy input');
        }
        $this->success = $match->match;
        $this->span = $span;
        $this->start = $span->start;
        $this->end = $span->end;
        $this->consumedByteLength = $span->byteLength();
        $this->farthestFailure = $match->farthestFailure;
        $this->diagnostic = $match->diagnostic;
        $this->node = $match->node;
    }

    public function rawText(): string
    {
        return $this->span->rawText();
    }

    public function token(): ?Token
    {
        return $this->match->token;
    }

    public function results(): array
    {
        return $this->match->results;
    }

    public function result(?string $name = null, $default = null)
    {
        return $this->match->getResult($name, $default);
    }
}
