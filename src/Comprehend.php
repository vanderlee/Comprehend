<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use ArgumentCountError;
use Closure;
use ReflectionFunction;
use UnexpectedValueException;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Parser\Terminal\Integer;
use Vanderlee\Comprehend\Result\ParseResult;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Syntax\Catalog;
use BadMethodCallException;

/**
 * Provides immutable fluent grammar authoring over the shared Parser graph. Magic calls use
 * Syntax and Catalog to construct parsers; FrozenGrammar handles reusable execution.
 *
 * @method static self text(string $text)
 * @method static self char(string $character, bool $include = true)
 * @method static self set(string $characters, bool $include = true)
 * @method static self range(?string $first, ?string $last, bool $in = true)
 * @method static self regex(string $pattern)
 * @method static self literalWindow(string $literal)
 * @method static self any()
 * @method static self nothing()
 * @method static self start()
 * @method static self end()
 * @method static self integer(?int $minimum = 0, ?int $maximum = null, int $base = 10, bool $allowLeadingZeros = false, string $digits = Integer::DIGITS)
 * @method static self binary(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false)
 * @method static self octal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false)
 * @method static self hexadecimal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false)
 * @method static self octet(bool $allowLeadingZeros = false)
 * @method static self sequence(self|Parser|string ...$parsers)
 * @method static self choice(self|Parser|string ...$parsers)
 * @method static self first(self|Parser|string ...$parsers)
 * @method static self longest(self|Parser|string ...$parsers)
 * @method static self shortest(self|Parser|string ...$parsers)
 * @method static self all(self|Parser|string ...$parsers)
 * @method static self except(self|Parser|string $match, self|Parser|string $not)
 * @method static self not(self|Parser|string $parser)
 * @method static self ref(AbstractRuleset $rules, string $name, mixed ...$arguments)
 * @method static self or(self|Parser|string ...$parsers)
 * @method static self repeat(mixed ...$arguments)
 * @method static self separatedBy(mixed ...$arguments)
 * @method static self some(mixed ...$arguments)
 * @method static self plus(mixed ...$arguments)
 * @method static self many(mixed ...$arguments)
 * @method static self star(mixed ...$arguments)
 * @method static self kleene(mixed ...$arguments)
 * @method static self maybe(mixed ...$arguments)
 * @method static self optional(mixed ...$arguments)
 * @method self then(self|Parser|string ...$next)
 * @method self thenIgnore(self|Parser|string $next)
 * @method self ignoreThen(self|Parser|string $next)
 * @method self between(self|Parser|string $open, self|Parser|string $close)
 * @method self prepend(self|Parser|string ...$before)
 * @method self named(string|int|callable $name, ?string $group = null, bool $leaf = false)
 * @method self tag(string ...$tags)
 * @method self mapValue(callable $mapper)
 * @method self mapText(callable $mapper)
 * @method self guard(callable $predicate)
 * @method self validateSemantic(callable $validator)
 * @method self onCommit(callable $effect)
 * @method self setResult(?string $key = null, mixed $value = null)
 * @method self concatResult(?string $key = null, mixed $value = null)
 * @method self pushResult(?string $key = null, mixed $value = null)
 * @method self diagnosticRule(string $name)
 * @method self caseSensitive(bool $sensitivity = true)
 * @method self prefer(ChoicePolicy $preference)
 * @method self spacing(self|Parser|null $trivia)
 * @method self lexeme()
 * @method self trivia()
 */
final class Comprehend
{
    private ?FrozenGrammar $preparedGrammar = null;
    private function __construct(private readonly Parser $parser, private readonly ?Syntax $syntax = null) {}

    public static function syntax(): Syntax
    {
        return Syntax::create();
    }

    /**
     * Names reserved by built-in factory and fluent dispatch.
     * @return list<string>
     */
    public static function syntaxNames(): array
    {
        return Catalog::builtinNames();
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): self
    {
        return self::callSyntax($name, $arguments);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        $name = strtolower($name);
        $fluent = $this->syntax === null
            ? Catalog::builtinFluent($name)
            : $this->syntax->fluent($name);
        if ($fluent !== null) {
            $result = self::invoke($fluent, [$this, ...$arguments]);

            return self::syntaxExpression($result, $name, $this->syntax);
        }

        // A static-only factory appends syntax; a fluent factory transforms this expression.
        $next = self::callSyntax($name, $arguments, $this->syntax);

        return $this->then($next);
    }

    /**
     * @internal Scoped vocabularies call this same typed dispatch boundary.
     * @param array<array-key, mixed> $arguments
     */
    public static function callSyntax(string $name, array $arguments, ?Syntax $syntax = null): self
    {
        $name = strtolower($name);
        $factory = $syntax === null ? Catalog::builtinStatic($name) : $syntax->factory($name);
        if ($factory === null) {
            throw new BadMethodCallException('Unknown syntax: ' . $name);
        }
        $result = self::invoke($factory, $arguments);

        return self::syntaxExpression($result, $name, $syntax);
    }

    private static function syntaxExpression(mixed $result, string $name, ?Syntax $syntax): self
    {
        if (
            !$result instanceof self
            && !$result instanceof Parser
            && !$result instanceof Definition
        ) {
            throw new UnexpectedValueException(
                'Syntax factories must return Comprehend, Parser or Definition: ' . $name,
            );
        }
        $expression = self::of($result);
        // Unscoped calls retain a returned expression's vocabulary. Scoped calls supply their own.
        if ($syntax === null || $syntax === $expression->syntax) {
            return $expression;
        }

        return new self($expression->parser, $syntax);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    private static function invoke(Closure $factory, array $arguments): mixed
    {
        $signature = new ReflectionFunction($factory);
        // PHP permits surplus positional arguments to user functions; authoring rejects them.
        // Unpacking below retains PHP's own named-argument and type checks.
        if (!$signature->isVariadic() && count($arguments) > $signature->getNumberOfParameters()) {
            throw new ArgumentCountError('Too many syntax arguments');
        }

        return $factory(...$arguments);
    }

    private function expression(Parser $parser): self
    {
        return new self($parser, $this->syntax);
    }

    public static function of(self|Parser|Definition $parser): self
    {
        if ($parser instanceof self) {
            return $parser;
        }
        if ($parser instanceof Definition) {
            $parser = $parser->implementation();
        }

        return new self((new GraphSnapshot(false))->copy($parser));
    }

    /** Export a detached object graph for Ruleset factories and object constructors. */
    public function parser(): Parser
    {
        return (new GraphSnapshot(false))->copy($this->parser);
    }

    public function assignTo(mixed &$variable): self
    {
        return $this->expression($this->parser()->assignTo($variable));
    }

    public function freeze(): FrozenGrammar
    {
        if ($this->preparedGrammar !== null) {
            return $this->preparedGrammar;
        }
        $grammar = $this->parser->freeze();
        if ($grammar->canReuseFor($this->parser)) {
            $this->preparedGrammar = $grammar;
        }

        return $grammar;
    }

    /** Complete-input parsing; owned built-in graphs reuse preparation, deferred/custom graphs refresh. */
    public function parse(
        string|Source $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        self|Parser|null $spacing = null,
    ): ParseResult {
        return $this->parseAll($source, $byteOffset, $limits, $spacing);
    }

    public function parseAll(
        string|Source $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        self|Parser|null $spacing = null,
    ): ParseResult {
        $grammar = $spacing === null ? $this : $this->spacing($spacing);

        return $grammar->freeze()->parseAll($source, $byteOffset, $limits);
    }

    public function matchPrefix(
        string|Source $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        self|Parser|null $spacing = null,
    ): ParseResult {
        $grammar = $spacing === null ? $this : $this->spacing($spacing);

        return $grammar->freeze()->matchPrefix($source, $byteOffset, $limits);
    }
}
