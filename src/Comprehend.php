<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use Vanderlee\Comprehend\Builder\AbstractRuleset;
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

/** Immutable authoring facade over the existing object parser types. */
final readonly class Comprehend
{
    private function __construct(private Parser $parser) {}

    public static function text(string $text): self
    {
        return self::of(new Text($text));
    }

    public static function of(Parser $parser): self
    {
        return new self((new GraphSnapshot(false))->copy($parser));
    }

    public static function regex(string $pattern): self
    {
        return self::of(new Regex($pattern));
    }

    public static function char(string $character, bool $include = true): self
    {
        return self::of(new Char($character, $include));
    }

    public static function set(string $characters, bool $include = true): self
    {
        return self::of(new Set($characters, $include));
    }

    public static function range(?string $first, ?string $last, bool $in = true): self
    {
        return self::of(new Range($first, $last, $in));
    }

    public static function any(): self
    {
        return self::of(new Any());
    }

    public static function nothing(): self
    {
        return self::of(new Nothing());
    }

    public static function start(): self
    {
        return self::of(new Start());
    }

    public static function end(): self
    {
        return self::of(new End());
    }

    public static function integer(?int $minimum = 0, ?int $maximum = null, int $base = 10): self
    {
        return self::of(new Integer($minimum, $maximum, $base));
    }

    public static function literalWindow(string $literal): self
    {
        return self::of(Regex::literalWindow($literal));
    }

    public static function sequence(self|Parser ...$parsers): self
    {
        return new self(new Sequence(...array_map(self::unwrap(...), $parsers)));
    }

    public static function choice(self|Parser ...$parsers): self
    {
        return new self(new Choice(...array_map(self::unwrap(...), $parsers)));
    }

    public static function firstOf(self|Parser ...$parsers): self
    {
        return new self(Choice::first(...array_map(self::unwrap(...), $parsers)));
    }

    public static function first(self|Parser ...$parsers): self
    {
        return self::firstOf(...$parsers);
    }

    public static function longestOf(self|Parser ...$parsers): self
    {
        return new self(Choice::longest(...array_map(self::unwrap(...), $parsers)));
    }

    public static function longest(self|Parser ...$parsers): self
    {
        return self::longestOf(...$parsers);
    }

    public static function shortestOf(self|Parser ...$parsers): self
    {
        return new self(Choice::shortest(...array_map(self::unwrap(...), $parsers)));
    }

    public static function shortest(self|Parser ...$parsers): self
    {
        return self::shortestOf(...$parsers);
    }

    public static function all(self|Parser ...$parsers): self
    {
        return new self(new All(...array_map(self::unwrap(...), $parsers)));
    }

    public static function except(self|Parser $match, self|Parser $not): self
    {
        return new self(new Except(self::unwrap($match), self::unwrap($not)));
    }

    public static function not(self|Parser $parser): self
    {
        return new self(new Not(self::unwrap($parser)));
    }

    public static function ref(AbstractRuleset $rules, string $name, mixed ...$arguments): self
    {
        return new self($rules->ref($name, ...$arguments));
    }

    public function then(self|Parser ...$next): self
    {
        if ($next === []) {
            return $this;
        }

        return new self(new Sequence($this->parser, ...array_map(self::unwrap(...), $next)));
    }

    public function or(self|Parser ...$next): self
    {
        if ($next === []) {
            return $this;
        }

        return new self(new Choice($this->parser, ...array_map(self::unwrap(...), $next)));
    }

    public function repeat(int $min = 0, ?int $max = null): self
    {
        return new self(new Repeat($this->parser, $min, $max));
    }

    public function plus(): self
    {
        return $this->repeat(1);
    }

    public function some(): self
    {
        return $this->plus();
    }

    public function star(): self
    {
        return $this->repeat();
    }

    public function many(): self
    {
        return $this->star();
    }

    public function optional(): self
    {
        return $this->repeat(0, 1);
    }

    public function kleene(): self
    {
        return $this->star();
    }

    public function maybe(): self
    {
        return $this->optional();
    }

    public function named(string|int $name, ?string $group = null, bool $leaf = false): self
    {
        return new self($this->parser()->name($name, $group, $leaf));
    }

    /** Export a detached object graph for Ruleset factories and object constructors. */
    public function parser(): Parser
    {
        return (new GraphSnapshot(false))->copy($this->parser);
    }

    public function mapValue(callable $mapper): self
    {
        return new self($this->parser()->mapValue($mapper));
    }

    public function guard(callable $predicate): self
    {
        return new self($this->parser()->guard($predicate));
    }

    public function validateSemantic(callable $validator): self
    {
        return new self($this->parser()->validateSemantic($validator));
    }

    public function onCommit(callable $effect): self
    {
        return new self($this->parser()->onCommit($effect));
    }

    public function setResult(?string $key = null, mixed $value = null): self
    {
        return new self($this->parser()->setResult($key, $value));
    }

    public function concatResult(?string $key = null, mixed $value = null): self
    {
        return new self($this->parser()->concatResult($key, $value));
    }

    public function pushResult(?string $key = null, mixed $value = null): self
    {
        return new self($this->parser()->pushResult($key, $value));
    }

    public function diagnosticRule(string $name): self
    {
        return new self($this->parser()->diagnosticRule($name));
    }

    public function caseSensitive(bool $sensitivity = true): self
    {
        return new self(new CaseSensitive($sensitivity, $this->parser));
    }

    public function prefer(string $preference): self
    {
        return new self(new Prefer($preference, $this->parser));
    }

    public function spacing(self|Parser|null $trivia): self
    {
        return new self(new Space($trivia === null ? null : self::unwrap($trivia), $this->parser));
    }

    public function lexeme(): self
    {
        return new self($this->parser->lexeme());
    }

    public function trivia(): self
    {
        return new self($this->parser->trivia());
    }

    public function freeze(): FrozenGrammar
    {
        return $this->parser->freeze();
    }

    private static function unwrap(self|Parser $parser): Parser
    {
        return $parser instanceof self ? $parser->parser : (new GraphSnapshot(false))->copy($parser);
    }
}
