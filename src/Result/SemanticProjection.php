<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use Closure;
use LogicException;
use Throwable;

/** One recognition-owned mapping; cache null, objects and failures as well as scalars. */
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

    public function value(ParseResult $result): mixed
    {
        if ($this->evaluating) { throw new LogicException('Recursive semantic value evaluation'); }
        if (!$this->evaluated) {
            $this->evaluating = true;
            try {
                $this->cached = $this->mapper === null ? $result->rawText() : ($this->mapper)($result);
            } catch (Throwable $error) {
                $this->error = $error;
            } finally {
                $this->evaluating = false;
                $this->evaluated = true;
            }
        }
        if ($this->error !== null) { throw $this->error; }
        return $this->cached;
    }
}
