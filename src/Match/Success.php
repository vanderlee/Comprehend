<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

use Closure;
use LogicException;
use UnexpectedValueException;
use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Match\Output\CallbackTrait;
use Vanderlee\Comprehend\Match\Output\ResultTrait;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\SemanticProjection;
use Vanderlee\Comprehend\Result\ValidationResult;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Throwable;

/**
 * Retains selected child matches, concrete syntax and lazy semantic operations. Supplies
 * ParseResult with cached values, validation and explicitly committed effects after recognition
 * succeeds.
 *
 * @author Martijn
 */
class Success extends AbstractMatch
{
    use ResultTrait;
    use CallbackTrait;

    private ?ExecutionState $state = null;
    /** @var (Closure(self): bool)|null */
    private static ?Closure $retainState = null;

    /**
     * Create a new match.
     *
     * @param int $length
     * @param Success[] $successes
     */
    public function __construct($length = 0, $successes = [])
    {
        parent::__construct($length);
        if ($successes !== []) {
            $state = $this->executionState();
            $state->successes = array_values($successes);
            $state->concreteMatches = $state->successes;
        }
    }

    /**
     * @internal Recognition children can differ from semantic output children.
     * @param list<Success> $matches
     */
    public function setConcreteMatches(array $matches): void
    {
        $state = $this->executionState();
        $state->concreteMatches = $matches;
    }

    /**
     * @internal Build without evaluating lazy semantic output or effects.
     * @param list<string> $tags
     */
    public function buildConcreteNode(
        Parser $parser,
        string|int|Closure|null $name = null,
        ?string $group = null,
        bool $leaf = false,
        array $tags = [],
    ): void {
        $span = $this->span;
        if ($span === null) {
            throw new LogicException('Concrete nodes require source-bound recognition');
        }

        $kind = $parser::class;
        $isTrivia = $parser instanceof Trivia;
        $children = $this->concreteChildNodes();
        if ($name instanceof Closure) {
            $name = $name(new ConcreteNode(
                $kind,
                $span,
                $children,
                $isTrivia,
                null,
                $group,
                $leaf,
                $tags,
            ));
            if (
                $name !== null
                && !is_string($name)
                && !is_int($name)
            ) {
                throw new UnexpectedValueException('Dynamic names must return string, int or null');
            }
        }

        $this->setConcreteNode(new ConcreteNode(
            $kind,
            $span,
            $children,
            $isTrivia,
            $name,
            $group,
            $leaf,
            $tags,
        ));
    }

    /** @return list<ConcreteNode> */
    private function concreteChildNodes(): array
    {
        $children = [];
        foreach ($this->state->concreteMatches ?? [] as $match) {
            if ($match->node === null) {
                throw new LogicException('Concrete children require source-bound recognition');
            }

            $children[] = $match->node;
        }

        return $children;
    }

    public function inheritValueFrom(self $child): void
    {
        if ($child->state === null && $child->node !== null) {
            return;
        }
        $state = $this->executionState();
        $state->valueChild = $child;
    }

    /** @internal Bind mapping and cache lifetime to this recognition, not the grammar. */
    public function setValueMapper(?callable $mapper): void
    {
        if ($mapper === null && $this->state === null) {
            return;
        }
        $state = $this->executionState();
        if ($mapper !== null) {
            $state->valueChild = null;
        }

        $state->semanticProjection = $mapper === null ? null : new SemanticProjection($mapper);
    }

    public function getValue(): mixed
    {
        $state = $this->executionState();
        if ($state->valueChild !== null) {
            return $state->valueChild->getValue();
        }

        $state->semanticProjection ??= new SemanticProjection(null);

        return $state->semanticProjection->value(new ParseResult($this));
    }

    /**
     * @param list<callable> $validators
     */
    public function addSemanticValidators(array $validators): void
    {
        if ($validators === []) {
            return;
        }
        $state = $this->executionState();
        $state->semanticValidators = array_merge($state->semanticValidators, $validators);
    }

    /** Explicit commits are idempotent; a failed effect sequence is never retried. */
    public function commit(): void
    {
        $this->effectExecution()->assertNotRunning();
        if (!$this->validateSemantic()->valid) {
            throw new LogicException('Semantic validation rejected commit');
        }

        $this->getCallback();
    }

    public function validateSemantic(): ValidationResult
    {
        $state = $this->executionState();
        if ($state->validationError !== null) {
            throw $state->validationError;
        }

        if ($state->validation !== null) {
            return $state->validation;
        }

        if ($state->validating) {
            throw new LogicException('Recursive semantic validation');
        }

        $state->validating = true;
        try {
            // Retain child-before-parent order even when a child reports rejection.
            $errors = $this->childValidationErrors();
            $errors = array_merge($errors, $this->localValidationErrors());

            return $state->validation = new ValidationResult($errors === [], $errors);
        } catch (Throwable $error) {
            $state->validationError = $error;
            throw $error;
        } finally {
            $state->validating = false;
        }
    }

    /**
     * A shared execution child contributes its validation errors only once.
     * @return list<string>
     */
    private function childValidationErrors(): array
    {
        $state = $this->executionState();
        $errors = [];
        $seen = [];
        foreach ($state->concreteMatches as $child) {
            $id = spl_object_id($child);
            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            foreach ($child->validateSemantic()->errors as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function localValidationErrors(): array
    {
        $state = $this->executionState();
        $errors = [];
        $result = new ParseResult($this);
        foreach ($state->semanticValidators as $validator) {
            $outcome = $validator($result);
            if ($outcome === false) {
                $errors[] = 'Semantic validation failed';
            } elseif (is_string($outcome)) {
                $errors[] = $outcome;
            } elseif ($outcome !== true) {
                throw new UnexpectedValueException('Semantic validators must return true, false or an error string');
            }
        }

        return $errors;
    }

    public function isCommitted(): bool
    {
        return $this->effectExecution()->isCompleted();
    }

    private function executionState(): ExecutionState
    {
        return $this->state ??= new ExecutionState();
    }

    /** @internal After CST construction, retain only children with deferred execution state. */
    public function retainExecutionState(): void
    {
        $state = $this->state;
        if ($state === null) {
            return;
        }
        $retain = self::$retainState ??= static fn(self $match): bool => $match->state !== null;
        if ($state->successes !== []) {
            $state->successes = array_values(array_filter($state->successes, $retain));
        }
        if ($state->concreteMatches !== []) {
            $state->concreteMatches = array_values(array_filter($state->concreteMatches, $retain));
        }
        if ($state->precedingMatches !== []) {
            $state->precedingMatches = array_values(array_filter($state->precedingMatches, $retain));
        }
        if ($state->isEmpty()) {
            $this->state = null;
        }
    }
}
