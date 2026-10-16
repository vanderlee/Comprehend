<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Builder\SnapshotParticipant;
use Vanderlee\Comprehend\Directive\Lexeme;
use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Result\Diagnostic;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Span;
use Closure;

/**
 * Defines the shared source-based recognition contract for terminals, combinators and
 * directives. Checks delegated matches, builds source-bound results and separates pure guards
 * from lazy semantics and explicit effects.
 */
abstract class Parser implements SnapshotParticipant
{
    use Output\ResultTrait;
    use Output\AssignTrait;

    /**
     * @var list<callable>
     */
    private array $effects = [];
    /**
     * @var callable|null
     */
    private $valueMapper = null;
    /**
     * @var list<callable>
     */
    private array $guards = [];
    /**
     * @var list<callable>
     */
    private array $semanticValidators = [];
    private string|int|Closure|null $nodeName = null;
    /**
     * @var list<string>
     */
    private array $nodeTags = [];
    private ?string $nodeGroup = null;
    private bool $semanticLeaf = false;
    private ?string $ruleName = null;

    protected static function parseCharacter(string $character): string
    {
        if ($character === '') {
            throw new InvalidArgumentException('Empty argument');
        }
        if (mb_check_encoding($character, 'UTF-8') ? mb_strlen($character, 'UTF-8') !== 1 : strlen($character) !== 1) {
            throw new InvalidArgumentException('Non-character argument');
        }

        return $character;
    }

    public function freeze(): FrozenGrammar
    {
        return FrozenGrammar::from($this);
    }

    public function snapshot(GraphSnapshot $context): Parser
    {
        $copy = clone $this;
        $context->registerCopy($this, $copy);
        $copy->copyDependencies($context);

        return $copy;
    }

    /** Override to detach owned grammar dependencies; call the parent implementation first. */
    protected function copyDependencies(GraphSnapshot $context): void
    {
        // Base metadata contains no owned grammar dependencies.
    }

    public function graphLabel(): string
    {
        return $this->diagnosticName();
    }

    /**
     * All direct parser dependencies, including stored policies. Override in composites.
     * Return stable parser identities without recognizing input or running semantic callbacks.
     * @return list<Parser>
     */
    public function children(): array
    {
        return [];
    }

    protected function diagnosticName(): string
    {
        $separator = strrpos(static::class, '\\');

        return $this->ruleName ?? ($separator === false ? static::class : substr(static::class, $separator + 1));
    }

    public function name(string|int|callable $name, ?string $group = null, bool $leaf = false): static
    {
        $this->nodeName = is_string($name) || is_int($name) ? $name : Closure::fromCallable($name);
        $this->nodeGroup = $group;
        $this->semanticLeaf = $leaf;

        return $this;
    }

    public function tag(string ...$tags): static
    {
        foreach ($tags as $tag) {
            if ($tag === '') {
                throw new InvalidArgumentException('Tags cannot be empty');
            }
        }
        $this->nodeTags = array_values(array_unique([...$this->nodeTags, ...$tags]));

        return $this;
    }

    public function hasName(): bool
    {
        return $this->nodeName !== null;
    }

    /** @internal Metadata makes a parser an observable composition boundary. */
    public function hasCompositionMetadata(): bool
    {
        return $this->nodeName !== null || $this->nodeTags !== [] || $this->nodeGroup !== null
            || $this->semanticLeaf || $this->ruleName !== null || $this->valueMapper !== null
            || $this->guards !== [] || $this->semanticValidators !== [] || $this->effects !== []
            || $this->resultCallbacks !== [] || $this->assignCallbacks !== [];
    }

    public function diagnosticRule(string $name): static
    {
        $this->ruleName = $name;

        return $this;
    }

    public function guard(callable $predicate): static
    {
        $this->guards[] = $predicate;

        return $this;
    }

    public function validateSemantic(callable $validator): static
    {
        $this->semanticValidators[] = $validator;

        return $this;
    }

    public function mapValue(callable $mapper): static
    {
        $this->valueMapper = $mapper;

        return $this;
    }

    /** Effects receive the selected source-bound ParseResult, only on commit. */
    public function onCommit(callable $effect): static
    {
        $this->effects[] = $effect;

        return $this;
    }

    public function lexeme(): Lexeme
    {
        return new Lexeme($this);
    }

    public function trivia(): Trivia
    {
        return new Trivia($this);
    }

    final public function matchPrefix(
        Source $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
    ): ParseResult {
        return new ParseResult(
            $this->recognizeInSession($source, $byteOffset, new ParseSession($source, limits: $limits)),
        );
    }

    /** @internal The only delegation path; custom parsers implement recognize(). */
    final public function recognizeInSession(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $session->enterParser($this, $offset);
        try {
            return $this->recognizeChecked($source, $offset, $session);
        } finally {
            $session->leaveParser($this, $offset);
        }
    }

    private function recognizeChecked(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $this->assertRecognitionPosition($source, $offset, $session);
        $match = $this->recognizeWithDiagnostics($source, $offset, $session);
        $this->assertReturnedMatch($source, $offset, $match);
        $match = $this->wrapReturnedChild($source, $offset, $match);
        $end = $match->accepted ? $offset + $match->length : $offset;
        $span = $match->span;
        $match->setSourceMetadata(
            $span !== null && $span->end === $end ? $span : $source->span($offset, $end),
            $session->farthestFailure(),
            $session->diagnostic(),
        );

        return $match instanceof Success
            ? $this->prepareSuccessfulMatch($source, $offset, $session, $match)
            : $match;
    }

    private function assertRecognitionPosition(Source $source, int $offset, ParseSession $session): void
    {
        if ($session->source() !== $source) {
            throw new InvalidArgumentException('Recognition must use the session source');
        }
        if ($offset < 0 || $offset > $source->byteLength()) {
            throw new InvalidArgumentException('Span must satisfy 0 <= start <= end <= source byte length');
        }
        if (!$source->isUnitBoundary($offset)) {
            throw new InvalidArgumentException('Parser position is inside a UTF-8 code point');
        }
    }

    private function recognizeWithDiagnostics(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $failureRevision = $session->failureRevision();
        $session->pushRule($this->diagnosticName());
        try {
            $match = $this->recognize($source, $offset, $session);
            if (
                !$match->accepted
                && $failureRevision === $session->failureRevision()
            ) {
                $session->recordFailure(
                    $this instanceof FailureDiagnosticAtStart
                        ? $offset
                        : $offset + $match->length,
                    $this->diagnosticExpectation(),
                );
            }
        } finally {
            $session->popRule();
        }

        return $match;
    }

    abstract protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch;

    protected function diagnosticExpectation(): string
    {
        return $this->diagnosticName();
    }

    private function assertReturnedMatch(Source $source, int $offset, AbstractMatch $match): void
    {
        if ($match->length > $source->byteLength() - $offset) {
            throw new InvalidArgumentException('Span must satisfy 0 <= start <= end <= source byte length');
        }
        if ($match->span !== null && ($match->span->source !== $source || $match->span->start !== $offset)) {
            throw new LogicException('Recognition returned a foreign source or start position');
        }
        if (!$source->isUnitBoundary($offset + $match->length)) {
            throw new LogicException('Parser returned a partial UTF-8 code point');
        }
    }

    private function wrapReturnedChild(Source $source, int $offset, AbstractMatch $match): AbstractMatch
    {
        // A directive may return an existing child execution record. Always
        // wrap it so its span, value cache, naming and lifecycle remain stable.
        if ($match instanceof Success && $match->node !== null) {
            $child = $match;
            $match = $this->success($source, $offset, $child->length, $child);
            if ($this->valueMapper === null) {
                $match->inheritValueFrom($child);
            }
        }

        return $match;
    }

    /**
     * @param list<Success>|Success $children
     */
    protected function success(Source $source, int $offset, int $length = 0, array|Success $children = []): Success
    {
        $success = new Success($length, is_array($children) ? $children : [$children]);
        $span = $source->span($offset, $offset + $length);
        $success->setSourceMetadata($span, null);
        $this->bindResultCallbacks($success, $span);
        $this->bindCommitEffects($success, $span);

        return $success;
    }

    private function bindResultCallbacks(Success $success, Span $span): void
    {
        if ($this->resultCallbacks === []) {
            return;
        }

        $resultCallbacks = array_map(static fn($factory) => $factory($span), $this->resultCallbacks);
        $success->addResultCallback(
            static function (&$results) use ($resultCallbacks) {
                foreach ($resultCallbacks as $callback) {
                    $callback($results);
                }
            },
        );
    }

    private function bindCommitEffects(Success $success, Span $span): void
    {
        if ($this->effects === [] && $this->assignCallbacks === []) {
            return;
        }

        // Snapshot registrations now; assignments and effects remain deferred until commit.
        $effects = $this->effects;
        $assignments = $this->assignCallbacks;
        $success->addCustomCallback(
            static function () use ($success, $span, $effects, $assignments) {
                foreach ($assignments as $assignment) {
                    $assignment($span->rawText());
                }
                $result = new ParseResult($success);
                foreach ($effects as $effect) {
                    $effect($result);
                }
            },
        );
    }

    private function prepareSuccessfulMatch(
        Source $source,
        int $offset,
        ParseSession $session,
        Success $match,
    ): AbstractMatch {
        $match->buildConcreteNode(
            $this,
            $this->nodeName,
            $this->nodeGroup,
            $this->semanticLeaf,
            $this->nodeTags,
        );
        $match->setValueMapper($this->valueMapper);
        $match->addSemanticValidators($this->semanticValidators);

        foreach ($this->guards as $guard) {
            if (!$guard($match->node)) {
                return $this->guardFailure($source, $offset, $session, $match);
            }
        }

        $match->retainExecutionState();

        return $match;
    }

    private function guardFailure(Source $source, int $offset, ParseSession $session, Success $match): Failure
    {
        $session->pushRule($this->diagnosticName());
        try {
            $session->recordFailure($offset + $match->length, 'guard(' . $this->diagnosticName() . ')');
        } finally {
            $session->popRule();
        }
        $failure = new Failure($match->length);
        $failure->setSourceMetadata(
            $source->span($offset, $offset),
            $session->farthestFailure(),
            $session->diagnostic(),
        );

        return $failure;
    }

    final public function parseAll(
        Source $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
    ): ParseResult {
        $match = $this->recognizeInSession($source, $byteOffset, new ParseSession($source, limits: $limits));
        if (!$match->accepted || $byteOffset + $match->length === $source->byteLength()) {
            return new ParseResult($match);
        }

        return new ParseResult($this->trailingInputFailure($source, $byteOffset, $match));
    }

    private function trailingInputFailure(Source $source, int $byteOffset, AbstractMatch $match): Failure
    {
        $end = $byteOffset + $match->length;
        $position = max($match->farthestFailure ?? $end, $end);
        $previous = $match->diagnostic;
        $expected = $position === $end ? ['end'] : [];
        if ($previous !== null && $previous->byteOffset === $position) {
            $expected = array_values(array_unique(array_merge($previous->expected, $expected)));
        }
        $failure = new Failure();
        $failure->setSourceMetadata(
            $source->span($byteOffset, $byteOffset),
            $position,
            new Diagnostic(
                $source,
                $position,
                $expected,
                $previous !== null && $previous->byteOffset === $position ? $previous->rulePaths : [],
            ),
        );

        return $failure;
    }

    abstract public function __toString(): string;

    /**
     * @param list<Success>|Success $children
     */
    protected function makeMatch(
        bool $accepted,
        Source $source,
        int $offset,
        int $length = 0,
        array|Success $children = [],
    ): AbstractMatch {
        return $accepted ? $this->success($source, $offset, $length, $children) : $this->failure($source, $offset);
    }

    protected function failure(Source $source, int $offset, int $progress = 0): Failure
    {
        return new Failure($progress);
    }
}
