<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use Closure;
use LogicException;
use Throwable;
use Vanderlee\Comprehend\Match\Success;

/**
 * Caches one recognition-owned semantic mapping for ParseResult. Retains nulls, objects and
 * thrown errors and guards against recursive evaluation so repeated value reads remain stable.
 */
final class SemanticProjection
{
    private readonly ?Closure $mapper;
    private bool $evaluating = false;
    private bool $evaluated = false;
    private mixed $cached = null;
    private ?Throwable $error = null;

    public function __construct(?callable $mapper)
    {
        $this->mapper = $mapper === null ? null : Closure::fromCallable($mapper);
    }

    public function value(ParseResult|Success $result): mixed
    {
        if ($this->evaluating) {
            throw new LogicException('Recursive semantic value evaluation');
        }
        if (!$this->evaluated) {
            // Cached reads and errors need no new public result wrapper.
            $this->evaluate($result instanceof Success ? new ParseResult($result) : $result);
        }
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->cached;
    }

    private function evaluate(ParseResult $result): void
    {
        $this->evaluating = true;
        try {
            $this->cached = $this->mapper === null ? $result->rawText() : ($this->mapper)($result);
        } catch (Throwable $error) {
            $this->error = $error;
        } finally {
            $this->evaluating = false;
            // Null values and thrown errors both complete this recognition's evaluation.
            $this->evaluated = true;
        }
    }
}
