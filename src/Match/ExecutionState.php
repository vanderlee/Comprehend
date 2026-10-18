<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

use Throwable;
use Vanderlee\Comprehend\Result\SemanticProjection;
use Vanderlee\Comprehend\Result\Recovery;
use Vanderlee\Comprehend\Result\ValidationResult;

/** @internal Mutable per-recognition state, allocated only for retained operations or their ancestors. */
final class ExecutionState
{
    /** @var list<Recovery> */
    public array $recoveries = [];
    /** @var list<Success> */
    public array $successes = [];
    /** @var list<Success> */
    public array $concreteMatches = [];
    /** @var list<callable> */
    public array $semanticValidators = [];
    public ?ValidationResult $validation = null;
    public bool $validating = false;
    public ?Throwable $validationError = null;
    public ?SemanticProjection $semanticProjection = null;
    public ?Success $valueChild = null;
    /** @var array<array-key, mixed>|null */
    public ?array $resultCache = null;
    public ?Throwable $resultError = null;
    public bool $evaluatingResults = false;
    /** @var list<callable> */
    public array $resultCallbacks = [];
    /** @var list<callable> */
    public array $customCallbacks = [];
    /** @var list<Success> */
    public array $precedingMatches = [];
    public ?EffectExecution $effectExecution = null;

    public function isEmpty(): bool
    {
        return $this->recoveries === []
            && $this->successes === []
            && $this->concreteMatches === []
            && $this->semanticValidators === []
            && $this->validation === null
            && !$this->validating
            && $this->validationError === null
            && $this->semanticProjection === null
            && $this->valueChild === null
            && $this->resultCache === null
            && $this->resultError === null
            && !$this->evaluatingResults
            && $this->resultCallbacks === []
            && $this->customCallbacks === []
            && $this->precedingMatches === []
            && $this->effectExecution === null;
    }
}
