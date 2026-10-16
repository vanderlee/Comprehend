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

    /**
     * Selected children used for named collections and effect traversal.
     *
     * @var Success[]
     */
    private array $successes;
    /**
     * All retained recognition children, including trivia and intersection checks.
     * Used for concrete syntax and semantic validation.
     * @var Success[]
     */
    private array $concreteMatches;
    /**
     * @var list<callable>
     */
    private array $semanticValidators = [];
    private ?ValidationResult $validation = null;
    private bool $validating = false;
    private ?Throwable $validationError = null;
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
        foreach ($this->concreteMatches as $match) {
            if ($match->node === null) {
                throw new LogicException('Concrete children require source-bound recognition');
            }

            $children[] = $match->node;
        }

        return $children;
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

        $this->semanticProjection = $mapper === null ? null : new SemanticProjection($mapper);
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
        $this->effectExecution()->assertNotRunning();
        if (!$this->validateSemantic()->valid) {
            throw new LogicException('Semantic validation rejected commit');
        }

        $this->getCallback();
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
            throw new LogicException('Recursive semantic validation');
        }

        $this->validating = true;
        try {
            // Retain child-before-parent order even when a child reports rejection.
            $errors = $this->childValidationErrors();
            $errors = array_merge($errors, $this->localValidationErrors());

            return $this->validation = new ValidationResult($errors === [], $errors);
        } catch (Throwable $error) {
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
                throw new UnexpectedValueException('Semantic validators must return true, false or an error string');
            }
        }

        return $errors;
    }

    public function isCommitted(): bool
    {
        return $this->effectExecution()->isCompleted();
    }
}
