<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Match;

use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Match\Output\CallbackTrait;
use Vanderlee\Comprehend\Match\Output\ResultTrait;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\SemanticProjection;
use Vanderlee\Comprehend\Result\ValidationResult;
use Vanderlee\Comprehend\Tree\ConcreteNode;

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

    /**
     * Whether the selected effect sequence has started.
     * A failed or completed sequence is never replayed.
     *
     * @var bool
     */
    private bool $resolved = false;
    /**
     * Successful selected execution children.
     *
     * @var Success[]
     */
    private array $successes;
    /** @var Success[] */
    private array $concreteMatches;
    /**
     * @var list<callable>
     */
    private array $semanticValidators = [];
    private ?ValidationResult $validation = null;
    private bool $validating = false;
    private ?\Throwable $validationError = null;
    private ?\Throwable $commitError = null;
    private bool $committing = false;
    private ?SemanticProjection $semanticProjection = null;
    private ?self $valueChild = null;

    /**
     * Create a new match.
     *
     * @param int $length
     * @param Success[] $successes
     */
    public function __construct($length = 0, $successes = [])
    {
        parent::__construct($length);
        $this->successes = $successes;
        $this->concreteMatches = $successes;
    }

    /**
     * @internal Recognition children can differ from semantic output children.
     * @param list<Success> $matches
     */
    public function setConcreteMatches(array $matches): void
    {
        $this->concreteMatches = $matches;
    }

    /**
     * @internal Build without evaluating lazy semantic output or effects.
     * @param list<string> $tags
     */
    public function buildConcreteNode(
        string $kind,
        string|int|\Closure|null $name = null,
        ?string $group = null,
        bool $leaf = false,
        array $tags = [],
    ): void {
        $span = $this->span;
        if ($span === null) {
            throw new \LogicException('Concrete nodes require source-bound recognition');
        }
        $children = [];
        foreach ($this->concreteMatches as $match) {
            if ($match->node === null) {
                throw new \LogicException('Concrete children require source-bound recognition');
            }
            $children[] = $match->node;
        }
        if ($name instanceof \Closure) {
            $name = $name(new ConcreteNode(
                $kind,
                $span,
                $children,
                $kind === Trivia::class,
                null,
                $group,
                $leaf,
                $tags,
            ));
            if ($name !== null && !is_string($name) && !is_int($name)) {
                throw new \UnexpectedValueException('Dynamic names must return string, int or null');
            }
        }
        $this->setConcreteNode(new ConcreteNode(
            $kind,
            $span,
            $children,
            $kind === Trivia::class,
            $name,
            $group,
            $leaf,
            $tags,
        ));
    }

    public function inheritValueFrom(self $child): void
    {
        $this->valueChild = $child;
    }

    /** @internal Bind mapping and cache lifetime to this recognition, not the grammar. */
    public function setValueMapper(?callable $mapper): void
    {
        if ($mapper !== null) {
            $this->valueChild = null;
        }
        $this->semanticProjection = new SemanticProjection($mapper);
    }

    public function getValue(): mixed
    {
        if ($this->valueChild !== null) {
            return $this->valueChild->getValue();
        }
        $this->semanticProjection ??= new SemanticProjection(null);

        return $this->semanticProjection->value(new ParseResult($this));
    }

    /**
     * @param list<callable> $validators
     */
    public function addSemanticValidators(array $validators): void
    {
        $this->semanticValidators = array_merge($this->semanticValidators, $validators);
    }

    /** Explicit commits are idempotent; a failed effect sequence is never retried. */
    public function commit(): void
    {
        if ($this->committing || $this->effectsProcessing) {
            throw new \LogicException('Recursive effect commit');
        }
        if ($this->commitError !== null) {
            throw $this->commitError;
        }
        if ($this->resolved) {
            return;
        }
        if (!$this->validateSemantic()->valid) {
            throw new \LogicException('Semantic validation rejected commit');
        }
        $this->resolved = true;
        $this->committing = true;
        try {
            $this->getCallback();
        } catch (\Throwable $error) {
            $this->commitError = $error;
            throw $error;
        } finally {
            $this->committing = false;
        }
    }

    public function validateSemantic(): ValidationResult
    {
        if ($this->validationError !== null) {
            throw $this->validationError;
        }
        if ($this->validation !== null) {
            return $this->validation;
        }
        if ($this->validating) {
            throw new \LogicException('Recursive semantic validation');
        }
        $this->validating = true;
        try {
            $errors = $this->childValidationErrors();
            $errors = array_merge($errors, $this->localValidationErrors());

            return $this->validation = new ValidationResult($errors === [], $errors);
        } catch (\Throwable $error) {
            $this->validationError = $error;
            throw $error;
        } finally {
            $this->validating = false;
        }
    }

    /**
     * A shared execution child contributes its validation errors only once.
     * @return list<string>
     */
    private function childValidationErrors(): array
    {
        $errors = [];
        $seen = [];
        foreach ($this->concreteMatches as $child) {
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
        $errors = [];
        $result = new ParseResult($this);
        foreach ($this->semanticValidators as $validator) {
            $outcome = $validator($result);
            if ($outcome === false) {
                $errors[] = 'Semantic validation failed';
            } elseif (is_string($outcome)) {
                $errors[] = $outcome;
            } elseif ($outcome !== true) {
                throw new \UnexpectedValueException('Semantic validators must return true, false or an error string');
            }
        }

        return $errors;
    }

    public function isCommitted(): bool
    {
        return $this->effectsProcessed
            && !$this->effectsProcessing
            && !$this->committing
            && $this->commitError === null
            && $this->effectError === null;
    }
}
