<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\Definition;
use Vanderlee\Comprehend\Builder\FrozenGrammar;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Directive\CaseSensitive;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Directive\Space;
use Vanderlee\Comprehend\Parser\Parser;
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
 * @method static self firstOf(self|Parser|string ...$parsers)
 * @method static self longest(self|Parser|string ...$parsers)
 * @method static self longestOf(self|Parser|string ...$parsers)
 * @method static self shortest(self|Parser|string ...$parsers)
 * @method static self shortestOf(self|Parser|string ...$parsers)
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
        $method = Catalog::continuationMethod($name);
        if ($method !== null) {
            return self::invoke($this->$method(...), $arguments);
        }
        $continuation = $this->syntax?->continuation($name);
        if ($continuation !== null) {
            return self::syntaxExpression(self::invoke($continuation, [$this, ...$arguments]), $name, $this->syntax);
        }
        return $this->then(self::callSyntax($name, $arguments, $this->syntax));
    }

    /** @internal Scoped vocabularies call this same typed dispatch boundary. */
    public static function callSyntax(string $name, array $arguments, ?Syntax $syntax = null): self
    {
        $name = strtolower($name);
        $method = Catalog::startMethod($name);
        $factory = $method === null ? $syntax?->factory($name) : self::$method(...);
        if ($factory === null) {
            throw new \BadMethodCallException('Unknown syntax: ' . $name);
        }
        $result = self::invoke($factory, $arguments);
        return self::syntaxExpression($result, $name, $syntax);
    }

    private static function syntaxExpression(mixed $result, string $name, ?Syntax $syntax): self
    {
        if (!$result instanceof self && !$result instanceof Parser && !$result instanceof Definition) {
            throw new \UnexpectedValueException('Syntax factories must return Comprehend, Parser or Definition: ' . $name);
        }
        $expression = self::of($result);
        return new self($expression->parser, $syntax ?? $expression->syntax);
    }

    private static function invoke(\Closure $factory, array $arguments): mixed
    {
        $signature = new \ReflectionFunction($factory);
        if (!$signature->isVariadic() && count($arguments) > $signature->getNumberOfParameters()) {
            throw new \ArgumentCountError('Too many syntax arguments');
        }
        return $factory(...$arguments);
    }

    private function expression(Parser $parser): self
    {
        return new self($parser, $this->syntax);
    }

    private static function makeText(string $text): self
    {
        return self::of(new Text($text));
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

    private static function makeRegex(string $pattern): self
    {
        return self::of(new Regex($pattern));
    }

    private static function makeChar(string $character, bool $include = true): self
    {
        return self::of(new Char($character, $include));
    }

    private static function makeSet(string $characters, bool $include = true): self
    {
        return self::of(new Set($characters, $include));
    }

    private static function makeRange(?string $first, ?string $last, bool $in = true): self
    {
        return self::of(new Range($first, $last, $in));
    }

    private static function makeAny(): self
    {
        return self::of(new Any());
    }

    private static function makeNothing(): self
    {
        return self::of(new Nothing());
    }

    private static function makeStart(): self
    {
        return self::of(new Start());
    }

    private static function makeEnd(): self
    {
        return self::of(new End());
    }

    private static function makeInteger(
        ?int $minimum = 0,
        ?int $maximum = null,
        int $base = 10,
        bool $allowLeadingZeros = false,
        string $digits = Integer::DIGITS,
    ): self {
        return self::of(new Integer($minimum, $maximum, $base, $allowLeadingZeros, $digits));
    }

    private static function makeBinary(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false): self
    {
        return self::makeInteger($minimum, $maximum, 2, $allowLeadingZeros);
    }

    private static function makeOctal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false): self
    {
        return self::makeInteger($minimum, $maximum, 8, $allowLeadingZeros);
    }

    private static function makeHexadecimal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false): self
    {
        return self::makeInteger($minimum, $maximum, 16, $allowLeadingZeros);
    }

    private static function makeOctet(bool $allowLeadingZeros = false): self
    {
        return self::makeInteger(0, 255, 10, $allowLeadingZeros);
    }

    private static function makeLiteralWindow(string $literal): self
    {
        return self::of(Regex::literalWindow($literal));
    }

    private static function makeSequence(self|Parser|string ...$parsers): self
    {
        return new self(new Sequence(...array_map(self::unwrap(...), $parsers)));
    }

    private static function makeChoice(self|Parser|string ...$parsers): self
    {
        return new self(new Choice(...array_map(self::unwrap(...), $parsers)));
    }

    private static function makeFirstOf(self|Parser|string ...$parsers): self
    {
        return new self(Choice::first(...array_map(self::unwrap(...), $parsers)));
    }

    private static function makeFirst(self|Parser|string ...$parsers): self
    {
        return self::makeFirstOf(...$parsers);
    }

    private static function makeLongestOf(self|Parser|string ...$parsers): self
    {
        return new self(Choice::longest(...array_map(self::unwrap(...), $parsers)));
    }

    private static function makeLongest(self|Parser|string ...$parsers): self
    {
        return self::makeLongestOf(...$parsers);
    }

    private static function makeShortestOf(self|Parser|string ...$parsers): self
    {
        return new self(Choice::shortest(...array_map(self::unwrap(...), $parsers)));
    }

    private static function makeShortest(self|Parser|string ...$parsers): self
    {
        return self::makeShortestOf(...$parsers);
    }

    private static function makeAll(self|Parser|string ...$parsers): self
    {
        return new self(new All(...array_map(self::unwrap(...), $parsers)));
    }

    private static function makeExcept(self|Parser|string $match, self|Parser|string $not): self
    {
        return new self(new Except(self::unwrap($match), self::unwrap($not)));
    }

    private static function makeNot(self|Parser|string $parser): self
    {
        return new self(new Not(self::unwrap($parser)));
    }

    private static function makeRef(AbstractRuleset $rules, string $name, mixed ...$arguments): self
    {
        return new self($rules->ref($name, ...$arguments));
    }

    public function then(self|Parser|string ...$next): self
    {
        if ($next === []) {
            return $this;
        }

        return $this->expression(new Sequence($this->parser, ...array_map(self::unwrap(...), $next)));
    }

    public function thenText(string $text): self
    {
        return $this->then(self::makeText($text));
    }

    public function thenChar(string $character, bool $include = true): self
    {
        return $this->then(self::makeChar($character, $include));
    }

    public function thenSet(string $characters, bool $include = true): self
    {
        return $this->then(self::makeSet($characters, $include));
    }

    public function thenRange(?string $first, ?string $last, bool $in = true): self
    {
        return $this->then(self::makeRange($first, $last, $in));
    }

    public function thenRegex(string $pattern): self
    {
        return $this->then(self::makeRegex($pattern));
    }

    public function thenLiteralWindow(string $literal): self
    {
        return $this->then(self::makeLiteralWindow($literal));
    }

    public function thenAny(): self
    {
        return $this->then(self::makeAny());
    }

    public function thenNothing(): self
    {
        return $this->then(self::makeNothing());
    }

    public function thenStart(): self
    {
        return $this->then(self::makeStart());
    }

    public function thenEnd(): self
    {
        return $this->then(self::makeEnd());
    }

    public function thenInteger(
        ?int $minimum = 0,
        ?int $maximum = null,
        int $base = 10,
        bool $allowLeadingZeros = false,
        string $digits = Integer::DIGITS,
    ): self {
        return $this->then(self::makeInteger($minimum, $maximum, $base, $allowLeadingZeros, $digits));
    }

    public function thenBinary(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false): self
    {
        return $this->then(self::makeBinary($minimum, $maximum, $allowLeadingZeros));
    }

    public function thenOctal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false): self
    {
        return $this->then(self::makeOctal($minimum, $maximum, $allowLeadingZeros));
    }

    public function thenHexadecimal(?int $minimum = 0, ?int $maximum = null, bool $allowLeadingZeros = false): self
    {
        return $this->then(self::makeHexadecimal($minimum, $maximum, $allowLeadingZeros));
    }

    public function thenOctet(bool $allowLeadingZeros = false): self
    {
        return $this->then(self::makeOctet($allowLeadingZeros));
    }

    private function chooseExpression(self|Parser|string ...$next): self
    {
        if ($next === []) {
            return $this;
        }

        return $this->expression(new Choice($this->parser, ...array_map(self::unwrap(...), $next)));
    }

    private function repeatExpression(int $min = 0, ?int $max = null): self
    {
        return $this->expression(new Repeat($this->parser, $min, $max));
    }

    public static function repeatOf(self|Parser|string $parser, int $min = 0, ?int $max = null): self
    {
        return new self(new Repeat(self::unwrap($parser), $min, $max), $parser instanceof self ? $parser->syntax : null);
    }

    public static function someOf(self|Parser|string $parser): self
    {
        return self::repeatOf($parser, 1);
    }

    public static function manyOf(self|Parser|string $parser): self
    {
        return self::repeatOf($parser);
    }

    public static function maybeOf(self|Parser|string $parser): self
    {
        return self::repeatOf($parser, 0, 1);
    }

    private function fluentPlus(): self
    {
        return $this->repeatExpression(1);
    }

    private function fluentSome(): self
    {
        return $this->fluentPlus();
    }

    private function fluentStar(): self
    {
        return $this->repeatExpression();
    }

    private function fluentMany(): self
    {
        return $this->fluentStar();
    }

    private function fluentOptional(): self
    {
        return $this->repeatExpression(0, 1);
    }

    private function fluentKleene(): self
    {
        return $this->fluentStar();
    }

    private function fluentMaybe(): self
    {
        return $this->fluentOptional();
    }

    public function named(string|int|callable $name, ?string $group = null, bool $leaf = false): self
    {
        return $this->expression($this->parser()->name($name, $group, $leaf));
    }

    public function tag(string ...$tags): self
    {
        return $this->expression($this->parser()->tag(...$tags));
    }

    /** Export a detached object graph for Ruleset factories and object constructors. */
    public function parser(): Parser
    {
        return (new GraphSnapshot(false))->copy($this->parser);
    }

    public function mapValue(callable $mapper): self
    {
        return $this->expression($this->parser()->mapValue($mapper));
    }

    public function guard(callable $predicate): self
    {
        return $this->expression($this->parser()->guard($predicate));
    }

    public function validateSemantic(callable $validator): self
    {
        return $this->expression($this->parser()->validateSemantic($validator));
    }

    public function onCommit(callable $effect): self
    {
        return $this->expression($this->parser()->onCommit($effect));
    }

    public function assignTo(mixed &$variable): self
    {
        return $this->expression($this->parser()->assignTo($variable));
    }

    public function setResult(?string $key = null, mixed $value = null): self
    {
        return $this->expression($this->parser()->setResult($key, $value));
    }

    public function concatResult(?string $key = null, mixed $value = null): self
    {
        return $this->expression($this->parser()->concatResult($key, $value));
    }

    public function pushResult(?string $key = null, mixed $value = null): self
    {
        return $this->expression($this->parser()->pushResult($key, $value));
    }

    public function diagnosticRule(string $name): self
    {
        return $this->expression($this->parser()->diagnosticRule($name));
    }

    public function caseSensitive(bool $sensitivity = true): self
    {
        return $this->expression(new CaseSensitive($sensitivity, $this->parser));
    }

    public function prefer(string $preference): self
    {
        return $this->expression(new Prefer($preference, $this->parser));
    }

    public function spacing(self|Parser|null $trivia): self
    {
        return $this->expression(new Space($trivia === null ? null : self::unwrap($trivia), $this->parser));
    }

    public function lexeme(): self
    {
        return $this->expression($this->parser->lexeme());
    }

    public function trivia(): self
    {
        return $this->expression($this->parser->trivia());
    }

    public function freeze(): FrozenGrammar
    {
        return $this->parser->freeze();
    }

    /** Complete-input parsing; strings are validated UTF-8. Freeze explicitly for reuse. */
    public function parse(string|Source $source, int $byteOffset = 0, ?ParseLimits $limits = null): ParseResult
    {
        return $this->parseAll($source, $byteOffset, $limits);
    }

    public function parseAll(string|Source $source, int $byteOffset = 0, ?ParseLimits $limits = null): ParseResult
    {
        return $this->freeze()->parseAll($source, $byteOffset, $limits);
    }

    public function matchPrefix(string|Source $source, int $byteOffset = 0, ?ParseLimits $limits = null): ParseResult
    {
        return $this->freeze()->matchPrefix($source, $byteOffset, $limits);
    }

    private static function unwrap(self|Parser|string $parser): Parser
    {
        if (is_string($parser)) {
            return new Text($parser);
        }

        return $parser instanceof self ? $parser->parser : (new GraphSnapshot(false))->copy($parser);
    }
}
