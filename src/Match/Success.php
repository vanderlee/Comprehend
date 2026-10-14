<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Match;

use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Tree\ConcreteNode;
use Vanderlee\Comprehend\Result\SemanticProjection;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\ValidationResult;
use ErrorException;
use Vanderlee\Comprehend\Match\Output\CallbackTrait;
use Vanderlee\Comprehend\Match\Output\ResultTrait;
/**
 * Successful match of a parser.
 *
 * @author Martijn
 */
class Success extends AbstractMatch
{
    use ResultTrait, CallbackTrait;
    /**
     * Whether the selected effect sequence has started.
     * A failed or completed sequence is never replayed.
     *
     * @var bool
     */
    private $resolved = false;
    /**
     * Successful selected execution children.
     *
     * @var Success[]
     */
    private $successes;
    private $concreteMatches;
    private array $semanticValidators = [];
    private ?ValidationResult $validation = null;
    private bool $validating = false;
    private ?\Throwable $validationError = null;
    private ?\Throwable $commitError = null;
    private bool $committing = false;
    public function addSemanticValidators(array $validators): void
    {
        $this->semanticValidators = array_merge($this->semanticValidators, $validators);
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
            $errors = [];
            $seen = [];
            foreach ($this->concreteMatches as $child) {
                $id = spl_object_id($child);
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $errors = array_merge($errors, $child->validateSemantic()->errors);
            }
            $result = new ParseResult($this);
            foreach ($this->semanticValidators as $validator) {
                $accepted = $validator($result);
                if ($accepted === false) {
                    $errors[] = 'Semantic validation failed';
                } elseif (is_string($accepted)) {
                    $errors[] = $accepted;
                } elseif ($accepted !== true) {
                    throw new \UnexpectedValueException('Semantic validators must return true, false or an error string');
                }
            }
            return $this->validation = new ValidationResult($errors === [], $errors);
        } catch (\Throwable $error) {
            $this->validationError = $error;
            throw $error;
        } finally {
            $this->validating = false;
        }
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
    public function isCommitted(): bool
    {
        return $this->effectsProcessed && !$this->effectsProcessing && !$this->committing && $this->commitError === null && $this->effectError === null;
    }
    private ?SemanticProjection $semanticProjection = null;
    private ?self $valueChild = null;
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
    /** @internal Recognition children can differ from semantic output children. */
    public function setConcreteMatches(array $matches): void
    {
        $this->concreteMatches = $matches;
    }
    /** @internal Build without evaluating lazy semantic output or effects. */
    public function buildConcreteNode(string $kind, string|int|null $name = null, ?string $group = null, bool $leaf = false): void
    {
        $children = [];
        foreach ($this->concreteMatches as $match) {
            if ($match->node === null) {
                throw new \LogicException('Concrete children require source-bound recognition');
            }
            $children[] = $match->node;
        }
        $this->setConcreteNode(new ConcreteNode($kind, $this->span, $children, $kind === Trivia::class, $name, $group, $leaf));
    }
    /** @internal Convert callback coordinates on the selected effect tree only. */
    /** @internal Raw text remains byte-based; only exposed numeric units change. */
    /**
     * Create a new match.
     *
     * @param int $length
     * @param Success[]|Success $successes
     */
    public function __construct($length = 0, $successes = [])
    {
        parent::__construct($length);
        $this->successes = $successes;
        $this->concreteMatches = $successes;
    }
    /**
     * Resolve any custom callbacks.
     *
     * @return $this|AbstractMatch
     * @throws ErrorException
     *
     */
}
