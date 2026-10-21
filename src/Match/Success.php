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
use Vanderlee\Comprehend\Result\Recovery;
use Vanderlee\Comprehend\Result\SemanticProjection;
use Vanderlee\Comprehend\Result\ValidationResult;
use Vanderlee\Comprehend\Result\ValidationIssue;
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
    /** @var list<Success> Children needed only until their immutable CST is built. */
    private array $concreteOnlyMatches = [];
    /** @var list<ConcreteNode> Optimized plain syntax without temporary execution records. */
    private array $concreteOnlyNodes = [];

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
            // Plain children need their trees, but no deferred execution state.
            // Keep custom records on the general path because they may override lifecycle behavior.
            if (self::arePlainMatches($successes)) {
                $this->concreteOnlyMatches = array_values($successes);

                return;
            }
            $state = $this->executionState();
            $state->successes = array_values($successes);
            $state->concreteMatches = $state->successes;
            $this->inheritRecoveries($state->successes);
        }
    }

    /** @param Success[] $matches */
    private static function arePlainMatches(array $matches): bool
    {
        foreach ($matches as $match) {
            if ($match::class !== self::class || $match->state !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @internal Recognition children can differ from semantic output children.
     * @param list<Success> $matches
     */
    public function setConcreteMatches(array $matches): void
    {
        $this->concreteOnlyNodes = [];
        if ($this->state === null && self::arePlainMatches($matches)) {
            $this->concreteOnlyMatches = $matches;

            return;
        }
        $this->concreteOnlyMatches = [];
        $state = $this->executionState();
        $state->concreteMatches = $matches;
        $this->inheritRecoveries($matches);
    }

    /**
     * @internal Replace concrete children with syntax that has no deferred operations.
     * @param list<ConcreteNode> $nodes
     */
    public function setConcreteNodes(array $nodes): void
    {
        $this->concreteOnlyMatches = [];
        $this->concreteOnlyNodes = $nodes;
        if ($this->state !== null) {
            $this->state->concreteMatches = [];
        }
    }

    /** @internal Whether replacing this record by its immutable syntax would lose deferred behavior. */
    public function hasDeferredState(): bool
    {
        return $this::class !== self::class || $this->state !== null;
    }

    /**
     * @internal Combine direct syntax with children that still own values, validation or effects.
     * @param list<Success|ConcreteNode> $children
     */
    public function setConcreteChildren(array $children): void
    {
        $matches = [];
        $nodes = [];
        foreach ($children as $child) {
            if ($child instanceof ConcreteNode) {
                $nodes[] = $child;
            } else {
                if ($child->node === null) {
                    throw new LogicException('Concrete children require source-bound recognition');
                }
                $matches[] = $child;
                $nodes[] = $child->node;
            }
        }
        $this->setConcreteMatches($matches);
        $this->concreteOnlyNodes = $nodes;
    }

    public function addRecovery(Recovery $recovery): void
    {
        $this->executionState()->recoveries[] = $recovery;
    }

    /** @return list<Recovery> */
    public function recoveries(): array
    {
        return $this->state->recoveries ?? [];
    }

    /** @param list<Success> $matches */
    private function inheritRecoveries(array $matches): void
    {
        $recoveries = [];
        foreach ($this->recoveries() as $recovery) {
            $recoveries[spl_object_id($recovery)] = $recovery;
        }
        foreach ($matches as $match) {
            foreach ($match->recoveries() as $recovery) {
                $recoveries[spl_object_id($recovery)] = $recovery;
            }
        }
        if ($recoveries !== []) {
            $ordered = array_values($recoveries);
            // Concrete trivia may be attached after semantic operands; report errors in source order.
            usort($ordered, static fn(Recovery $a, Recovery $b): int => $a->span->start <=> $b->span->start);
            $this->executionState()->recoveries = $ordered;
        }
    }

    /** @internal Exact built-in records without parser-level node metadata or semantic operations. */
    public function preparePlainNode(Parser $parser): void
    {
        $span = $this->span;
        if ($span === null) {
            throw new LogicException('Concrete nodes require source-bound recognition');
        }
        $this->node = new ConcreteNode(
            $parser->concreteKind(),
            $span,
            $this->concreteChildNodes(),
            $parser instanceof Trivia,
        );
        // Recognizers can install deferred operations even without parser-level metadata.
        $this->concreteOnlyMatches = [];
        $this->concreteOnlyNodes = [];
        if ($this->state !== null) {
            $this->retainExecutionState();
        }
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

        $kind = $parser->concreteKind();
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
        if ($this->concreteOnlyNodes !== []) {
            return $this->concreteOnlyNodes;
        }
        $children = [];
        $matches = $this->concreteOnlyMatches !== []
            ? $this->concreteOnlyMatches
            : ($this->state->concreteMatches ?? []);
        foreach ($matches as $match) {
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

    /** @internal Select a narrower child value without another projection when its getter caches it. */
    public function selectCachedValueFrom(self $child): bool
    {
        if (
            $this::class !== self::class
            || $child::class !== self::class
            || $child->state?->valueChild !== null
        ) {
            return false;
        }
        $state = $this->executionState();
        $state->semanticProjection = null;
        $state->valueChild = $child;

        return true;
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

    /** Capture the previous projection before installing the next cached stage. */
    public function mapValue(callable $transform): void
    {
        $state = $this->executionState();
        $child = $state->valueChild;
        $projection = $state->semanticProjection ?? new SemanticProjection(null);
        $this->setValueMapper(static fn(ParseResult $result): mixed => $transform(
            $child !== null ? $child->getValue() : $projection->value($result),
        ));
    }

    public function getValue(): mixed
    {
        $state = $this->executionState();
        if ($state->valueChild !== null) {
            return $state->valueChild->getValue();
        }

        $state->semanticProjection ??= new SemanticProjection(null);

        return $state->semanticProjection->value($this);
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
            if ($state->recoveries !== []) {
                return $state->validation = new ValidationResult(array_map(
                    static fn(Recovery $recovery): ValidationIssue => new ValidationIssue(
                        'syntax.recovered',
                        $recovery->diagnostic->message(),
                        $recovery->span,
                    ),
                    $state->recoveries,
                ));
            }
            // Retain child-before-parent order even when a child reports rejection.
            $issues = $this->childValidationIssues();
            $issues = array_merge($issues, $this->localValidationIssues());

            return $state->validation = new ValidationResult($issues);
        } catch (Throwable $error) {
            $state->validationError = $error;
            throw $error;
        } finally {
            $state->validating = false;
        }
    }

    /**
     * A shared execution child contributes each issue only once, including through multiple parents.
     * @return list<ValidationIssue>
     */
    private function childValidationIssues(): array
    {
        $state = $this->executionState();
        $issues = [];
        $seen = [];
        $seenIssues = [];
        foreach ($state->concreteMatches as $child) {
            $id = spl_object_id($child);
            if (isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            foreach ($child->validateSemantic()->issues as $issue) {
                $issue = $this->checkValidationIssue($issue);
                $id = spl_object_id($issue);
                if (!isset($seenIssues[$id])) {
                    $seenIssues[$id] = true;
                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<ValidationIssue>
     */
    private function localValidationIssues(): array
    {
        $state = $this->executionState();
        if ($state->semanticValidators === []) {
            return [];
        }
        $issues = [];
        $result = new ParseResult($this);
        foreach ($state->semanticValidators as $validator) {
            $outcome = $validator($result);
            if ($outcome === true) {
                continue;
            }
            if ($outcome === false || is_string($outcome)) {
                $outcome = new ValidationIssue(
                    'validation.failed',
                    $outcome === false ? 'Semantic validation failed' : $outcome,
                    $result->span,
                );
            }
            if ($outcome instanceof ValidationIssue) {
                $outcome = [$outcome];
            }
            if (!is_array($outcome) || !array_is_list($outcome)) {
                throw new UnexpectedValueException(
                    'Semantic validators must return a boolean, string, issue or issue list',
                );
            }
            foreach ($outcome as $issue) {
                $issues[] = $this->checkValidationIssue($issue);
            }
        }

        return $issues;
    }

    private function checkValidationIssue(mixed $issue): ValidationIssue
    {
        if (!$issue instanceof ValidationIssue) {
            throw new UnexpectedValueException('Validation issue lists must contain only ValidationIssue objects');
        }
        if ($issue->span->source !== $this->span?->source) {
            throw new UnexpectedValueException('Validation issues must belong to the recognized source');
        }

        return $issue;
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
        $this->concreteOnlyMatches = [];
        $this->concreteOnlyNodes = [];
        $state = $this->state;
        if ($state === null) {
            return;
        }
        // Build packed lists directly instead of filtering and then reindexing.
        if ($state->successes !== []) {
            $retained = [];
            foreach ($state->successes as $child) {
                if ($child->state !== null) {
                    $retained[] = $child;
                }
            }
            $state->successes = $retained;
        }
        if ($state->concreteMatches !== []) {
            $retained = [];
            foreach ($state->concreteMatches as $child) {
                // Custom execution records may implement validation outside the built-in state.
                if ($child::class !== self::class) {
                    $retained[] = $child;
                    continue;
                }
                $childState = $child->state;
                if (
                    $childState !== null && (
                        $childState->semanticValidators !== []
                        || $childState->recoveries !== []
                        || $childState->concreteMatches !== []
                        || $childState->validation !== null
                        || $childState->validationError !== null
                    )
                ) {
                    $retained[] = $child;
                }
            }
            $state->concreteMatches = $retained;
        }
        if ($state->precedingMatches !== []) {
            $retained = [];
            foreach ($state->precedingMatches as $child) {
                if ($child->state !== null) {
                    $retained[] = $child;
                }
            }
            $state->precedingMatches = $retained;
        }
        if ($state->isEmpty()) {
            $this->state = null;
        }
    }
}
