<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use InvalidArgumentException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Source\Span;
use Vanderlee\Comprehend\Tree\AstNode;
use Vanderlee\Comprehend\Tree\ConcreteNode;

/**
 * Exposes the canonical source-bound outcome of recognition. Wraps an internal match to provide
 * diagnostics, concrete and named trees, cached semantics, validation and explicit commit.
 * @template-covariant T = mixed
 */
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
    private ?ValidationResult $syntaxValidation = null;

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
        if ($this->node === null) {
            return null;
        }

        return $this->ast ??= $this->node->ast();
    }

    public function rawText(): string
    {
        return $this->span->rawText();
    }

    /** @return list<Recovery> Selected recovery actions, in source order. */
    public function recoveries(): array
    {
        return $this->match instanceof Success ? $this->match->recoveries() : [];
    }

    public function hasRecovery(): bool
    {
        return $this->recoveries() !== [];
    }

    /** @return list<Diagnostic> Recovery errors, or the terminal diagnostic on failure. */
    public function diagnostics(): array
    {
        $diagnostics = array_map(
            static fn(Recovery $recovery): Diagnostic => $recovery->diagnostic,
            $this->recoveries(),
        );
        if (!$this->success && $this->diagnostic !== null) {
            $diagnostics[] = $this->diagnostic;
        }

        return $diagnostics;
    }

    /**
     * Failed results have no value; successful unmapped results yield original text.
     * @return T|null
     */
    public function value(): mixed
    {
        return $this->match instanceof Success ? $this->match->getValue() : null;
    }

    /**
     * Short value access: original text by default, mapped value when configured.
     * @return T|null
     */
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
        if (!$this->match instanceof Success) {
            $offset = $this->diagnostic->byteOffset ?? $this->start;

            return $this->syntaxValidation ??= new ValidationResult([new ValidationIssue(
                'syntax.rejected',
                'Syntax was not accepted',
                $this->span->source->span($offset, $offset),
            )]);
        }

        return $this->match->validateSemantic();
    }

    public function isCommitted(): bool
    {
        return $this->match instanceof Success && $this->match->isCommitted();
    }

    /**
     * @return array<array-key, mixed>
     */
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
