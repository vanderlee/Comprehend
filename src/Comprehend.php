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
use Vanderlee\Comprehend\Runtime\ParseLimits;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Syntax\Catalog;

/**
 * Immutable syntax authoring; magic calls only construct the existing parser graph.
 * Factory methods append syntax when called fluently. Repetition dispatch has
 * distinct static (item first) and fluent (current expression) signatures.
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
 * @method static self some(mixed ...$arguments)
 * @method static self plus(mixed ...$arguments)
 * @method static self many(mixed ...$arguments)
 * @method static self star(mixed ...$arguments)
 * @method static self kleene(mixed ...$arguments)
 * @method static self maybe(mixed ...$arguments)
 * @method static self optional(mixed ...$arguments)
 * @method self then(self|Parser|string ...$next)
 * @method self named(string|int|callable $name, ?string $group = null, bool $leaf = false)
 * @method self tag(string ...$tags)
 * @method self mapValue(callable $mapper)
 * @method self guard(callable $predicate)
 * @method self validateSemantic(callable $validator)
 * @method self onCommit(callable $effect)
 * @method self setResult(?string $key = null, mixed $value = null)
 * @method self concatResult(?string $key = null, mixed $value = null)
 * @method self pushResult(?string $key = null, mixed $value = null)
 * @method self diagnosticRule(string $name)
 * @method self caseSensitive(bool $sensitivity = true)
 * @method self prefer(string $preference)
 * @method self spacing(self|Parser|null $trivia)
 * @method self lexeme()
 * @method self trivia()
 */
final readonly class Comprehend
{
    private function __construct(private Parser $parser, private ?Syntax $syntax = null) {}

    public static function syntax(): Syntax
    {
        return Syntax::create();
    }

    /** Names reserved by built-in factory and fluent dispatch. */
    public static function syntaxNames(): array
    {
        return Catalog::builtinNames();
    }

    public static function __callStatic(string $name, array $arguments): self
    {
        return self::callSyntax($name, $arguments);
    }

    public function __call(string $name, array $arguments): self
    {
        $name = strtolower($name);
        $continuation = $this->syntax === null
            ? Catalog::builtinContinuation($name)
            : $this->syntax->continuation($name);
        if ($continuation !== null) {
            return self::syntaxExpression(self::invoke($continuation, [$this, ...$arguments]), $name, $this->syntax);
        }
        return $this->then(self::callSyntax($name, $arguments, $this->syntax));
    }

    /** @internal Scoped vocabularies call this same typed dispatch boundary. */
    public static function callSyntax(string $name, array $arguments, ?Syntax $syntax = null): self
    {
        $name = strtolower($name);
        $factory = $syntax === null ? Catalog::builtinStart($name) : $syntax->factory($name);
        if ($factory === null) {
            throw new \BadMethodCallException('Unknown syntax: ' . $name);
        }
        $result = self::invoke($factory, $arguments);
        return self::syntaxExpression($result, $name, $syntax);
    }

    private static function syntaxExpression(mixed $result, string $name, ?Syntax $syntax): self
    {
        if (!$result instanceof self && !$result instanceof Parser && !$result instanceof Definition) {
            throw new UnexpectedValueException('Syntax factories must return Comprehend, Parser or Definition: ' . $name);
        }
        $expression = self::of($result);
        if ($syntax === null || $syntax === $expression->syntax) {
            return $expression;
        }
        return new self($expression->parser, $syntax);
    }

    private static function invoke(Closure $factory, array $arguments): mixed
    {
        $signature = new ReflectionFunction($factory);
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
        return $this->parser->freeze();
    }

    /** Complete-input parsing; strings are validated UTF-8. Freeze explicitly for reuse. */
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
