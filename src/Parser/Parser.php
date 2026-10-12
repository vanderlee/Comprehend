<?php

namespace Vanderlee\Comprehend\Parser;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Builder\Implementation;
use Vanderlee\Comprehend\Builder\Ruleset;
use Vanderlee\Comprehend\Core\Context;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Library\Rfc2234;
use Vanderlee\Comprehend\Library\Rfc3513;
use Vanderlee\Comprehend\Library\Rfc3986;
use Vanderlee\Comprehend\Library\Rfc4234;
use Vanderlee\Comprehend\Library\Rfc4408;
use Vanderlee\Comprehend\Library\Rfc5234;
use Vanderlee\Comprehend\Library\Rfc7405;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Failure;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Output\AssignTrait;
use Vanderlee\Comprehend\Parser\Output\ResultTrait;
use Vanderlee\Comprehend\Parser\Output\TokenTrait;
use Vanderlee\Comprehend\Parser\Structure\All;
use Vanderlee\Comprehend\Parser\Structure\Choice;
use Vanderlee\Comprehend\Parser\Structure\Except;
use Vanderlee\Comprehend\Parser\Structure\Not;
use Vanderlee\Comprehend\Parser\Structure\Repeat;
use Vanderlee\Comprehend\Parser\Structure\Sequence;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Integer;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Start;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Source\ByteSource;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Source\Utf8Source;
use Vanderlee\Comprehend\Result\ParseResult;

abstract class Parser
{
    use ResultTrait, TokenTrait, AssignTrait;

    /**
     * List of callbacks to call when this parser has matched a part of the full parse.
     *
     * @var callable[]
     */
    private $callbacks = [];
    private ?string $diagnosticRule = null;

    /** Assign a readable rule name for recognition paths. */
    public function diagnosticRule(string $name): self
    {
        $this->diagnosticRule = $name;
        return $this;
    }

    private const SOURCE_PARSERS = [
        Ruleset::class,
        Implementation::class,
        CaseSensitive::class,
        Prefer::class,
        Space::class,
        Stub::class,
        All::class,
        Choice::class,
        Except::class,
        Not::class,
        Repeat::class,
        Sequence::class,
        Any::class,
        Char::class,
        End::class,
        Integer::class,
        Nothing::class,
        Range::class,
        Regex::class,
        Set::class,
        Start::class,
        Text::class,
        Rfc2234::class,
        Rfc3513::class,
        Rfc3986::class,
        Rfc4234::class,
        Rfc4408::class,
        Rfc5234::class,
        Rfc7405::class,
    ];

    /** Custom subclasses must explicitly opt in to canonical byte coordinates. */
    protected function supportsSource(): bool
    {
        return in_array(static::class, self::SOURCE_PARSERS, true);
    }

    /** Delegate recognition without resolving effects, preserving legacy overrides. */
    protected function parseInContext(&$input, $offset, Context $context)
    {
        $source = $context->source();
        if ($source === null) {
            return $this->parse($input, $offset, $context);
        }

        if (!$this->supportsSource() && !$context->allowsLegacyAscii()) {
            throw new LogicException('Custom parser must declare source compatibility: ' . static::class);
        }

        $source->span($offset, $offset);
        if ($source instanceof Utf8Source && !$source->isCodePointBoundary($offset)) {
            throw new InvalidArgumentException('Parser position is inside a UTF-8 code point');
        }

        $revision = $context->failureRevision();
        $context->pushRule($this->diagnosticName());
        try {
            $match = $this->parse($input, $offset, $context);
            if (!$match->match && $revision === $context->failureRevision()) {
                $context->recordFailure($this instanceof Not || $this instanceof Except
                    ? $offset : $offset + $match->length, $this->diagnosticExpectation());
            }
        } finally {
            $context->popRule();
        }
        if ($input !== $source->sliceBytes(0, $source->byteLength())) {
            throw new LogicException('Source-compatible parsers must not mutate input');
        }

        $source->span($offset, $offset + $match->length);
        if ($source instanceof Utf8Source && !$source->isCodePointBoundary($offset + $match->length)) {
            throw new LogicException('Parser returned a partial UTF-8 code point');
        }

        $match->setSourceMetadata($source->span($offset, $match->match ? $offset + $match->length : $offset),
            $context->farthestFailure(), $context->diagnostic());

        if ($match instanceof Success) {
            $match->buildConcreteNode(static::class);
        }

        return $match;
    }

    /** Label custom rules without stringifying potentially recursive grammars. */
    protected function diagnosticName(): string
    {
        return $this->diagnosticRule ?? substr(strrchr(static::class, '\\'), 1);
    }

    protected function diagnosticExpectation(): string
    {
        return str_contains(static::class, '\\Terminal\\')
            ? (string) $this : $this->diagnosticName();
    }

    /** Canonical prefix result; selected callbacks resolve before returning. */
    public function parseSource(Source $source, int $byteOffset = 0): ParseResult
    {
        return $this->matchPrefix($source, $byteOffset);
    }

    /** Recognize a prefix; remaining input is allowed. */
    public function matchPrefix(Source $source, int $byteOffset = 0): ParseResult
    {
        return new ParseResult($this->matchSource($source, $byteOffset));
    }

    /** Consume the entire remainder, including trivia explicitly present in the grammar. */
    public function parseAll(Source $source, int $byteOffset = 0): ParseResult
    {
        return new ParseResult($this->matchAllSourceUnresolved($source, $byteOffset)->resolve());
    }

    /** @internal Check EOF before resolving any selected callbacks. */
    public function matchAllSourceUnresolved(Source $source, int $byteOffset = 0, bool $legacyAscii = false): AbstractMatch
    {
        $match = $legacyAscii ? $this->matchAsciiUnresolved($source, $byteOffset)
            : $this->matchSourceUnresolved($source, $byteOffset);
        if (!$match->match || $match->span->end === $source->byteLength()) {
            return $match;
        }

        $failure = new Failure($match->length);
        $position = max($match->farthestFailure ?? $match->span->end, $match->span->end);
        $previous = $match->diagnostic;
        $expected = $position === $match->span->end ? ['end'] : [];
        if ($previous !== null && $previous->byteOffset === $position) {
            $expected = array_values(array_unique(array_merge($previous->expected, $expected)));
        }
        $diagnostic = new \Vanderlee\Comprehend\Result\Diagnostic($source, $position, $expected,
            $previous !== null && $previous->byteOffset === $position ? $previous->rulePaths : []);
        $failure->setSourceMetadata($source->span($byteOffset, $byteOffset), $position, $diagnostic);
        return $failure;
    }

    /** @internal ASCII-only bridge for unmigrated parser subclasses. */
    public function matchAsciiUnresolved(Source $source, int $byteOffset = 0): AbstractMatch
    {
        $input = $source->sliceBytes(0, $source->byteLength());

        return $this->parseInContext($input, $byteOffset, new Context(null, true, Prefer::FIRST, $source, true));
    }

    /** Recognize a prefix with explicit source mode and canonical byte coordinates. */
    public function matchSource(Source $source, int $byteOffset = 0): AbstractMatch
    {
        return $this->matchSourceUnresolved($source, $byteOffset)->resolve();
    }

    /** @internal Independent source context, with effects left unresolved. */
    public function matchSourceUnresolved(Source $source, int $byteOffset = 0): AbstractMatch
    {
        $input = $source->sliceBytes(0, $source->byteLength());

        return $this->parseInContext(
            $input,
            $byteOffset,
            new Context(
                null, true,
                Prefer::FIRST, $source
            )
        );
    }

    protected static function parseCharacter($character)
    {
        if ($character === '' || $character === null) {
            throw new InvalidArgumentException('Empty argument');
        }

        if (is_int($character)) {
            return chr($character);
        } elseif (mb_check_encoding($character, 'UTF-8')
            ? mb_strlen($character, 'UTF-8') > 1 : strlen($character) > 1) {
            throw new InvalidArgumentException('Non-character argument');
        }

        return $character;
    }

    /**
     * Match the input with this parser, starting from the offset position.
     *
     * @param string $input
     * @param int $offset
     * @param Context $context
     *
     * @return AbstractMatch
     */
    abstract protected function parse(&$input, $offset, Context $context);

    /**
     * @param string $input
     * @param int $offset
     *
     * @return AbstractMatch;
     */
    public function match($input, $offset = 0)
    {
        return $this->matchUnresolved($input, $offset)->resolve();
    }

    /**
     * Recognize in an independent context without resolving callbacks.
     * Used for speculative spacing; the owning match defers its effects.
     *
     * @return AbstractMatch
     * @internal
     */
    public function matchUnresolved($input, $offset = 0)
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Negative offset');
        }

        return $this->parse($input, $offset, new Context());
    }

    public function __invoke($input, $offset = 0)
    {
        return $this->match($input, $offset);
    }

    /**
     * Create a match.
     *
     * @param bool $success
     * @param string $input
     * @param int $offset
     * @param int $length
     * @param Success[]|Success $successes
     *
     * @return AbstractMatch
     */
    protected function makeMatch($success, &$input, $offset, $length = 0, &$successes = [])
    {
        return $success
            ? $this->success($input, $offset, $length, $successes)
            : $this->failure($input, $offset);
    }

    /**
     * Create a successful match.
     *
     * @param string $input
     * @param int $offset
     * @param int $length
     * @param Success[]|Success $successes
     *
     * @return Success
     */
    protected function success($input, $offset, $length = 0, &$successes = [])
    {
        $successes = is_array($successes)
            ? $successes
            : [$successes];

        $success = new Success($length, $successes);
        $span = (new ByteSource($input))->span($offset, $offset + $length);

        // ResultTrait
        $success->addResultCallback(function (&$results) use ($span) {
            $text = $span->rawText();

            $this->resolveResultCallbacks($results, $text);
        });

        // AssignTrait
        $callbacks = $this->callbacks;
        $success->addCustomCallback(function () use ($input, $offset, $length, $callbacks, $span, $success) {
            $text = $span->rawText();

            $this->resolveAssignCallbacks($text);

            foreach ($callbacks as $callback) {
                [$callbackOffset, $callbackLength] = $success->callbackCoordinates($offset, $length);
                $callback($text, $input, $callbackOffset, $callbackLength);
            }
        });

        // TokenTrait
        $success->setTokenCallback(function (&$children) use ($input, $offset, $length) {
            return $this->resolveToken($input, $offset, $length, $children, get_class($this));
        });

        return $success;
    }

    /**
     * Create a failed match.
     *
     * @param string $input
     * @param int $offset
     * @param int $length
     *
     * @return Failure
     */
    protected function failure(&$input, $offset, $length = 0)
    {
        return new Failure($length);
    }

    /**
     * Assign a function to be called if (and only if) this parser matched successfully as part of the whole syntax.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function callback(callable $callback)
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    abstract public function __toString();
}
