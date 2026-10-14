<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser;

use Vanderlee\Comprehend\Directive\Trivia;
use Vanderlee\Comprehend\Directive\Lexeme;
use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Result\Diagnostic;
/** Source-based recognition; semantic reads and committed effects are separate. */
abstract class Parser
{
    use Output\ResultTrait, Output\AssignTrait;
    private array $effects = [];
    private $valueMapper = null;
    private array $guards = [];
    private array $semanticValidators = [];
    private string|int|null $nodeName = null;
    private ?string $nodeGroup = null;
    private bool $semanticLeaf = false;
    private ?string $ruleName = null;
    public function name(string|int $name, ?string $group = null, bool $leaf = false): static
    {
        $this->nodeName = $name;
        $this->nodeGroup = $group;
        $this->semanticLeaf = $leaf;
        return $this;
    }
    public function hasName(): bool
    {
        return $this->nodeName !== null;
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
    final public function matchPrefix(Source $source, int $byteOffset = 0): ParseResult
    {
        return new ParseResult($this->recognizeInSession($source, $byteOffset, new ParseSession($source)));
    }
    final public function parseAll(Source $source, int $byteOffset = 0): ParseResult
    {
        $match = $this->recognizeInSession($source, $byteOffset, new ParseSession($source));
        if (!$match->accepted || $match->span->end === $source->byteLength()) {
            return new ParseResult($match);
        }
        $position = max($match->farthestFailure ?? $match->span->end, $match->span->end);
        $previous = $match->diagnostic;
        $expected = $position === $match->span->end ? ['end'] : [];
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
        return new ParseResult($failure);
    }
    /** @internal The only delegation path; custom parsers implement recognize(). */
    final public function recognizeInSession(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        if ($session->source() !== $source) {
            throw new InvalidArgumentException('Recognition must use the session source');
        }
        $source->span($offset, $offset);
        if (!$source->isUnitBoundary($offset)) {
            throw new InvalidArgumentException('Parser position is inside a UTF-8 code point');
        }
        $revision = $session->failureRevision();
        $session->pushRule($this->diagnosticName());
        try {
            $match = $this->recognize($source, $offset, $session);
            if (!$match->accepted && $revision === $session->failureRevision()) {
                $session->recordFailure(
                    $this instanceof Structure\Not || $this instanceof Structure\Except ? $offset : $offset + $match->length,
                    $this->diagnosticExpectation(),
                );
            }
        } finally {
            $session->popRule();
        }
        $source->span($offset, $offset + $match->length);
        if ($match->span !== null && ($match->span->source !== $source || $match->span->start !== $offset)) {
            throw new LogicException('Recognition returned a foreign source or start position');
        }
        if (!$source->isUnitBoundary($offset + $match->length)) {
            throw new LogicException('Parser returned a partial UTF-8 code point');
        }
        // A directive may return an existing child execution record. Always
        // wrap it so its span, value cache, naming and lifecycle remain stable.
        if ($match instanceof Success && $match->node !== null) {
            $child = $match;
            $match = $this->success($source, $offset, $child->length, $child);
            if ($this->valueMapper === null) {
                $match->inheritValueFrom($child);
            }
        }
        $match->setSourceMetadata(
            $source->span($offset, $match->accepted ? $offset + $match->length : $offset),
            $session->farthestFailure(),
            $session->diagnostic(),
        );
        if ($match instanceof Success) {
            $match->buildConcreteNode(static::class, $this->nodeName, $this->nodeGroup, $this->semanticLeaf);
            $match->setValueMapper($this->valueMapper);
            $match->addSemanticValidators($this->semanticValidators);
            foreach ($this->guards as $guard) {
                if (!$guard($match->node)) {
                    $session->pushRule($this->diagnosticName());
                    try {
                        $session->recordFailure($offset + $match->length, 'guard(' . $this->diagnosticName() . ')');
                    } finally {
                        $session->popRule();
                    }
                    $failure = new Failure($match->length);
                    $failure->setSourceMetadata($source->span($offset, $offset), $session->farthestFailure(), $session->diagnostic());
                    return $failure;
                }
            }
        }
        return $match;
    }
    protected function diagnosticName(): string
    {
        $separator = strrpos(static::class, '\\');
        return $this->ruleName ?? ($separator === false ? static::class : substr(static::class, $separator + 1));
    }
    protected function diagnosticExpectation(): string
    {
        return str_contains(static::class, '\Terminal\\') ? (string) $this : $this->diagnosticName();
    }
    abstract protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch;
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
    protected function makeMatch(bool $accepted, Source $source, int $offset, int $length = 0, array|Success $children = []): AbstractMatch
    {
        return $accepted ? $this->success($source, $offset, $length, $children) : $this->failure($source, $offset);
    }
    protected function success(Source $source, int $offset, int $length = 0, array|Success $children = []): Success
    {
        $success = new Success($length, is_array($children) ? $children : [$children]);
        $span = $source->span($offset, $offset + $length);
        $success->setSourceMetadata($span, null);
        $resultCallbacks = array_map(static fn($factory) => $factory($span), $this->resultCallbacks);
        $success->addResultCallback(
            static function (&$results) use ($span, $resultCallbacks) {
                foreach ($resultCallbacks as $callback) {
                    $callback($results);
                }
            },
        );
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
        return $success;
    }
    protected function failure(Source $source, int $offset, int $progress = 0): Failure
    {
        return new Failure($progress);
    }
    abstract public function __toString(): string;
}
