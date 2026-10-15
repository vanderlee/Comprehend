<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use InvalidArgumentException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Tree\AstNode;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/** Canonical source-bound result; all outputs derive from its CST and selected execution. */
final class ParseResult
{
    public readonly bool $success;
    public readonly bool $ok;
    public readonly Span $span;
    public readonly int $start;
    public readonly int $end;
    public readonly int $consumedByteLength;
    public readonly ?int $farthestFailure;
    public readonly ?Diagnostic $diagnostic;
    public readonly ?ConcreteNode $node;
    private ?AstNode $ast = null;

    public function __construct(private readonly AbstractMatch $match)
    {
        $span = $match->span;
        if ($span === null) {
            throw new InvalidArgumentException('ParseResult requires a source-bound recognition');
        }
        $this->success = $match->accepted;
        $this->ok = $this->success;
        $this->span = $span;
        $this->start = $span->start;
        $this->end = $span->end;
        $this->consumedByteLength = $span->byteLength();
        $this->farthestFailure = $match->farthestFailure;
        $this->diagnostic = $match->diagnostic;
        $this->node = $match->node;
    }

    public function ast(): ?AstNode
    {
        return $this->node === null ? null : $this->ast ??= $this->node->ast();
    }

    public function rawText(): string
    {
        return $this->span->rawText();
    }

    /** Failed results have no value; successful unmapped results yield original text. */
    public function value(): mixed
    {
        return $this->match instanceof Success ? $this->match->getValue() : null;
    }

    /** Short value access: original text by default, mapped value when configured. */
    public function output(): mixed
    {
        return $this->value();
    }

    /** False means syntax or semantic validation rejected commit. */
    public function commit(): bool
    {
        if (!$this->match instanceof Success || !$this->validate()->valid) {
            return false;
        }
        $this->match->commit();
        return true;
    }

    public function validate(): ValidationResult
    {
        return $this->match instanceof Success
            ? $this->match->validateSemantic()
            : new ValidationResult(false, ['Syntax was not accepted']);
    }

    public function isCommitted(): bool
    {
        return $this->match instanceof Success && $this->match->isCommitted();
    }

    public function results(): array
    {
        $results = $this->match->getResults();
        unset($results['']);
        return $results;
    }

    public function result(?string $name = null, mixed $default = null): mixed
    {
        return $this->match->getResult($name, $default);
    }
}
