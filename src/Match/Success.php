<?php

namespace Vanderlee\Comprehend\Match;

use ErrorException;
use Vanderlee\Comprehend\Match\Output\CallbackTrait;
use Vanderlee\Comprehend\Match\Output\ResultTrait;
use Vanderlee\Comprehend\Match\Output\TokenTrait;

/**
 * Successful match of a parser.
 *
 * @author Martijn
 */
class Success extends AbstractMatch
{
    use TokenTrait, ResultTrait, CallbackTrait;

    /**
     * Boolean state indicating whether this match has been resolved already.
     * Each match may only be resolved once to prevent conflicts.
     *
     * @var bool
     */
    private $resolved = false;

    /**
     * Any successful matches tbat make up this success.
     *
     * @var Success[]
     */
    private $successes;
    private $coordinates;
    private $concreteMatches;
    private array $semanticValidators = [];
    private ?\Vanderlee\Comprehend\Result\ValidationResult $validation = null;
    private bool $validating = false;
    private ?\Throwable $validationError = null;
    private ?\Throwable $commitError = null;
    private bool $committing = false;

    public function addSemanticValidators(array $validators): void
    {
        $this->semanticValidators = array_merge($this->semanticValidators, $validators);
    }

    public function validateSemantic(): \Vanderlee\Comprehend\Result\ValidationResult
    {
        if ($this->validationError !== null) { throw $this->validationError; }
        if ($this->validation !== null) { return $this->validation; }
        if ($this->validating) { throw new \LogicException('Recursive semantic validation'); }
        $this->validating = true;
        try {
            $errors = [];
            $seen = [];
            foreach (array_merge($this->concreteMatches, $this->precedingMatches) as $child) {
                $id = spl_object_id($child);
                if (isset($seen[$id])) { continue; }
                $seen[$id] = true;
                $errors = array_merge($errors, $child->validateSemantic()->errors);
            }
            $result = new \Vanderlee\Comprehend\Result\ParseResult($this);
            foreach ($this->semanticValidators as $validator) {
                $accepted = $validator($result);
                if ($accepted === false) { $errors[] = 'Semantic validation failed'; }
                elseif (is_string($accepted)) { $errors[] = $accepted; }
                elseif ($accepted !== true) { throw new \UnexpectedValueException('Semantic validators must return true, false or an error string'); }
            }
            return $this->validation = new \Vanderlee\Comprehend\Result\ValidationResult($errors === [], $errors);
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
        if ($this->committing) { throw new \LogicException('Recursive effect commit'); }
        if ($this->commitError !== null) { throw $this->commitError; }
        if (!$this->resolved) { $this->resolve(); }
    }

    public function isCommitted(): bool { return $this->resolved && !$this->committing && $this->commitError === null; }
    private ?\Vanderlee\Comprehend\Result\SemanticProjection $semanticProjection = null;

    /** @internal Bind mapping and cache lifetime to this recognition, not the grammar. */
    public function setValueMapper(?callable $mapper): void
    {
        $this->semanticProjection = new \Vanderlee\Comprehend\Result\SemanticProjection($mapper);
    }

    public function getValue(): mixed
    {
        $this->semanticProjection ??= new \Vanderlee\Comprehend\Result\SemanticProjection(null);
        return $this->semanticProjection->value(new \Vanderlee\Comprehend\Result\ParseResult($this));
    }

    /** @internal Recognition children can differ from semantic output children. */
    public function setConcreteMatches(array $matches): void
    {
        $this->concreteMatches = $matches;
    }

    /** @internal Build without evaluating any lazy semantic or token callbacks. */
    public function buildConcreteNode(string $kind, string|int|null $name = null, ?string $group = null, bool $isTerminal = false): void
    {
        // Directives and stubs can return their child's match object unchanged.
        $children = $this->node !== null ? [$this->node] : [];
        $transparent = $this->node !== null;
        $tokenIndexes = $transparent ? [0] : [];
        if ($this->node === null) {
            foreach ($this->concreteMatches as $match) {
                if ($match->node === null) {
                    throw new \LogicException('Concrete children require source-bound recognition');
                }
                $children[] = $match->node;
            }
            foreach ($this->successes as $match) {
                $index = array_search($match, $this->concreteMatches, true);
                if ($index === false) {
                    throw new \LogicException('Token children must belong to the concrete recognition tree');
                }
                $tokenIndexes[] = $index;
            }
        }
        $this->setConcreteNode(new \Vanderlee\Comprehend\Tree\ConcreteNode($kind, $this->span, $children,
            $kind === \Vanderlee\Comprehend\Directive\Trivia::class, $name, $group, $isTerminal,
            $transparent, $tokenIndexes));
        $this->tokenCallback = null;
    }

    /** @internal Convert callback coordinates on the selected effect tree only. */
    public function useCoordinates(\Vanderlee\Comprehend\Compatibility\Coordinates $coordinates): void
    {
        if ($this->span === null || $this->span->source !== $coordinates->source) {
            throw new \InvalidArgumentException('Cannot convert an unbound or foreign-source match');
        }
        $coordinates->length($this->span->start, $this->span->end);
        foreach (array_merge($this->successes, $this->precedingMatches) as $child) {
            $child->useCoordinates($coordinates);
        }
        $this->coordinates = $coordinates;
    }

    /** @internal Raw text remains byte-based; only exposed numeric units change. */
    public function callbackCoordinates(int $offset, int $length): array
    {
        return $this->coordinates === null ? [$offset, $length]
            : [$this->coordinates->position($offset), $this->coordinates->length($offset, $offset + $length)];
    }

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

    public function __get(string $name)
    {
        switch ($name) {
            case 'match':
                return true;
            case 'results':
                $results = $this->getResults();
                unset($results[null]);

                return $results;
            case 'result':
                $results = $this->getResults();

                return $results[null]
                    ?? null;
            case 'token':
                return $this->getToken();
        }

        return parent::__get($name);
    }

    /**
     * Resolve any custom callbacks.
     *
     * @return $this|AbstractMatch
     * @throws ErrorException
     *
     */
    public function resolve(): AbstractMatch
    {
        if ($this->resolved) {
            throw new ErrorException('Match already resolved');
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

        return $this;
    }

    public function __toString(): string
    {
        return 'Successfully matched ' . $this->length . ' characters';
    }
}
