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
use Vanderlee\Comprehend\Builder\FreezeFlags;
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
 * @template T = mixed
 * @mixin \Vanderlee\Comprehend\Syntax\ValueMappings<T>
 *
 * @method static self<string> text(string $text)
 * @method static self<string> char(string $character, bool $include = true)
 * @method static self<string> set(string $characters, bool $include = true)
 * @method static self<string> range(?string $first, ?string $last, bool $in = true)
 * @method static self<string> regex(string $pattern)
 * @method static self<string> readWhile(callable $predicate, int $min = 0, ?int $max = null)
 * @method static self<string> readUntil(string|callable $delimiter, int $min = 0, ?int $max = null)
 * @method static self<string> readWhileSet(string $characters, int $min = 0, ?int $max = null)
 * @method static self<string> readWhileRange(?string $first, ?string $last, int $min = 0, ?int $max = null)
 * @method static self<string> readRest()
 * @method static self<null> skipWhile(callable $predicate, int $min = 0, ?int $max = null)
 * @method static self<null> skipUntil(string|callable $delimiter, int $min = 0, ?int $max = null)
 * @method static self<null> skipRest()
 * @method static self<string> literalWindow(string $literal)
 * @method static self<string> any()
 * @method static self<string> nothing()
 * @method static self<string> start()
 * @method static self<string> end()
 * @method static self<string> integer(?int $minimum = 0, ?int $maximum = null, int $base = 10, bool $allowLeadingZeros = false, string $digits = Integer::DIGITS)
 * @method static self<string> binary(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false)
 * @method static self<string> octal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false)
 * @method static self<string> hexadecimal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false)
 * @method static self<string> octet(bool $allowLeadingZeros = false)
 * @method static self<string> sequence(self|Parser|string ...$parsers)
 * @method static self<string> choice(self|Parser|string ...$parsers)
 * @method static self<string> first(self|Parser|string ...$parsers)
 * @method static self<string> longest(self|Parser|string ...$parsers)
 * @method static self<string> shortest(self|Parser|string ...$parsers)
 * @method static self<string> all(self|Parser|string ...$parsers)
 * @method static self<string> except(self|Parser|string $match, self|Parser|string $not)
 * @method static self<string> not(self|Parser|string $parser)
 * @method static self ref(AbstractRuleset $rules, string $name, mixed ...$arguments)
 * @method static self or(self|Parser|string ...$parsers)
 * @method static self repeat(mixed ...$arguments)
 * @method static self recoverUntil(mixed ...$arguments)
 * @method static self recoverDelimited(mixed ...$arguments)
 * @method static self recoverItem(mixed ...$arguments)
 * @method static self recoverWith(mixed ...$arguments)
 * @method static self some(mixed ...$arguments)
 * @method static self plus(mixed ...$arguments)
 * @method static self many(mixed ...$arguments)
 * @method static self star(mixed ...$arguments)
 * @method static self kleene(mixed ...$arguments)
 * @method static self maybe(mixed ...$arguments)
 * @method static self optional(mixed ...$arguments)
 * @method static self<list<mixed>> separatedBy(mixed ...$arguments)
 * @method self<T> thenIgnore(self|Parser|string $next)
 * @method self<mixed> ignoreThen(self|Parser|string $next)
 * @method self<T> between(self|Parser|string $open, self|Parser|string $close)
 * @method self then(self|Parser|string ...$next)
 * @method self prepend(self|Parser|string ...$before)
 * @method self<T> named(string|int|callable $name, ?string $group = null, bool $leaf = false)
 * @method self<T> tag(string ...$tags)
 * @method self<T> guard(callable $predicate)
 * @method self<T> validateSemantic(callable $validator)
 * @method self<T> onCommit(callable $effect)
 * @method self<T> setResult(?string $key = null, mixed $value = null)
 * @method self<T> concatResult(?string $key = null, mixed $value = null)
 * @method self<T> pushResult(?string $key = null, mixed $value = null)
 * @method self<T> diagnosticRule(string $name)
 * @method self<T> caseSensitive(bool $sensitivity = true)
 * @method self<T> prefer(ChoicePolicy $preference)
 * @method self<T> spacing(self|Parser|null $trivia)
 * @method self<T> lexeme()
 * @method self<T> trivia()
 */
final class Comprehend
{
    /** @var array<int, FrozenGrammar> Reusable preparation is isolated by its complete flag set. */
    private array $preparedGrammars = [];
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
        $factory = $syntax === null ? Catalog::builtinRule($name) : $syntax->rule($name);
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

    /**
     * @template U
     * @param self<U>|Parser|Definition $parser
     * @return ($parser is self<U> ? self<U> : self<mixed>)
     */
    public static function of(self|Parser|Definition $parser, ?Syntax $syntax = null): self
    {
        if ($parser instanceof self) {
            return $syntax === null || $syntax === $parser->syntax ? $parser : new self($parser->parser, $syntax);
        }
        if ($parser instanceof Definition) {
            $parser = $parser->implementation();
        }

        return new self((new GraphSnapshot(false))->copy($parser), $syntax);
    }

    /** Export a detached object graph for Ruleset factories and object constructors. */
    public function parser(): Parser
    {
        return (new GraphSnapshot(false))->copy($this->parser);
    }

    /** @return self<T> */
    public function assignTo(mixed &$variable): self
    {
        return $this->expression($this->parser()->assignTo($variable));
    }

    /** @return FrozenGrammar<T> */
    public function freeze(int $flags = FreezeFlags::DEFAULT): FrozenGrammar
    {
        FreezeFlags::validate($flags);
        if (isset($this->preparedGrammars[$flags])) {
            return $this->preparedGrammars[$flags];
        }
        $grammar = $this->parser->freeze($flags);
        if ($grammar->canReuseFor($this->parser)) {
            $this->preparedGrammars[$flags] = $grammar;
        }

        return $grammar;
    }

    /**
     * Complete-input parsing; reuse-capable graphs cache preparation, other graphs refresh.
     * @return ParseResult<T>
     */
    public function parse(
        string|Source $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        self|Parser|null $spacing = null,
    ): ParseResult {
        return $this->parseAll($source, $byteOffset, $limits, $spacing);
    }

    /** @return ParseResult<T> */
    public function parseAll(
        string|Source $source,
        int $byteOffset = 0,
        ?ParseLimits $limits = null,
        self|Parser|null $spacing = null,
    ): ParseResult {
        $grammar = $spacing === null ? $this : $this->spacing($spacing);

        return $grammar->freeze()->parseAll($source, $byteOffset, $limits);
    }

    /** @return ParseResult<T> */
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
