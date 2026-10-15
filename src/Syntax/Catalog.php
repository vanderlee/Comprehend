<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Closure;
use InvalidArgumentException;
use Vanderlee\Comprehend\Builder\AbstractRuleset;
use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Comprehend;
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
use Vanderlee\Comprehend\Syntax;

/** Coordinates built-in dispatch and immutable custom vocabularies. */
final class Catalog
{
    private const BUILTINS = [
        'text',
        'regex',
        'char',
        'set',
        'range',
        'any',
        'nothing',
        'start',
        'end',
        'integer',
        'binary',
        'octal',
        'hexadecimal',
        'octet',
        'literalwindow',
        'sequence',
        'choice',
        'first',
        'longest',
        'shortest',
        'all',
        'except',
        'not',
        'ref',
        'or',
        'repeat',
        'some',
        'many',
        'maybe',
    ];

    private const CONTINUATIONS = [
        'then',
        'named',
        'tag',
        'mapvalue',
        'guard',
        'validatesemantic',
        'oncommit',
        'setresult',
        'concatresult',
        'pushresult',
        'diagnosticrule',
        'casesensitive',
        'prefer',
        'spacing',
        'lexeme',
        'trivia',
    ];

    private const ALIASES = [
        'plus' => 'some',
        'star' => 'many',
        'kleene' => 'many',
        'optional' => 'maybe',
    ];

    public function __construct(private readonly array $starts = [], private readonly array $continuations = []) {}

    public static function canonicalName(string $name): string
    {
        $name = strtolower($name);
        return self::ALIASES[$name] ?? $name;
    }

    public static function builtinStart(string $name): ?Closure
    {
        return match (self::canonicalName($name)) {
            'text' => static fn(string $text): Parser => new Text($text),
            'regex' => static fn(string $pattern): Parser => new Regex($pattern),
            'char' => static fn(string $character, bool $include = true): Parser => new Char($character, $include),
            'set' => static fn(string $characters, bool $include = true): Parser => new Set($characters, $include),
            'range' => static fn(?string $first, ?string $last, bool $in = true): Parser => new Range(
                $first,
                $last,
                $in,
            ),
            'any' => static fn(): Parser => new Any(),
            'nothing' => static fn(): Parser => new Nothing(),
            'start' => static fn(): Parser => new Start(),
            'end' => static fn(): Parser => new End(),
            'integer' => static fn(
                ?int $minimum = 0,
                ?int $maximum = null,
                int $base = 10,
                bool $allowLeadingZeros = false,
                string $digits = Integer::DIGITS,
            ): Parser => new Integer($minimum, $maximum, $base, $allowLeadingZeros, $digits),
            'binary' => static fn(
                ?int $minimum = 0,
                ?int $maximum = null,
                bool $allowLeadingZeros = false,
            ): Parser => new Integer($minimum, $maximum, 2, $allowLeadingZeros),
            'octal' => static fn(
                ?int $minimum = 0,
                ?int $maximum = null,
                bool $allowLeadingZeros = false,
            ): Parser => new Integer($minimum, $maximum, 8, $allowLeadingZeros),
            'hexadecimal' => static fn(
                ?int $minimum = 0,
                ?int $maximum = null,
                bool $allowLeadingZeros = false,
            ): Parser => new Integer($minimum, $maximum, 16, $allowLeadingZeros),
            'octet' => static fn(bool $allowLeadingZeros = false): Parser => new Integer(
                0,
                255,
                10,
                $allowLeadingZeros,
            ),
            'literalwindow' => static fn(string $literal): Parser => Regex::literalWindow($literal),
            'sequence' => static fn(Comprehend|Parser|string ...$parsers): Parser => self::sequence($parsers),
            'choice', 'or' => static fn(Comprehend|Parser|string ...$parsers): Parser => self::choice($parsers),
            'first' => static fn(Comprehend|Parser|string ...$parsers): Parser => self::choice($parsers)->preferFirst(),
            'longest' => static fn(Comprehend|Parser|string ...$parsers): Parser
                => self::choice($parsers)->preferLongest(),
            'shortest' => static fn(Comprehend|Parser|string ...$parsers): Parser
                => self::choice($parsers)->preferShortest(),
            'all' => static fn(Comprehend|Parser|string ...$parsers): Parser => new All(
                ...
                array_map(self::parser(...), $parsers),
            ),
            'except' => static fn(Comprehend|Parser|string $match, Comprehend|Parser|string $not): Parser => new Except(
                self::parser($match),
                self::parser($not),
            ),
            'not' => static fn(Comprehend|Parser|string $parser): Parser => new Not(self::parser($parser)),
            'ref' => static fn(AbstractRuleset $rules, string $name, mixed ...$arguments): Parser => $rules->ref(
                $name,
                ...
                $arguments,
            ),
            'repeat' => static fn(
                Comprehend|Parser|string $parser,
                int $min = 0,
                ?int $max = null,
            ): Comprehend => ($parser instanceof Comprehend ? $parser : Comprehend::of(self::parser($parser)))->repeat(
                $min,
                $max,
            ),
            'some' => static fn(Comprehend|Parser|string $parser): Comprehend => Comprehend::repeat($parser, 1),
            'many' => static fn(Comprehend|Parser|string $parser): Comprehend => Comprehend::repeat($parser),
            'maybe' => static fn(Comprehend|Parser|string $parser): Comprehend => Comprehend::repeat($parser, 0, 1),
            default => null,
        };
    }

    public static function builtinContinuation(string $name): ?Closure
    {
        return match (self::canonicalName($name)) {
            'then' => static fn(
                Comprehend $item,
                Comprehend|Parser|string ...$next,
            ): Comprehend|Parser => $next === [] ? $item : self::sequence([$item, ...$next]),
            'or' => static fn(
                Comprehend $item,
                Comprehend|Parser|string ...$next,
            ): Comprehend|Parser => $next === [] ? $item : self::choice([$item, ...$next]),
            'repeat' => static fn(Comprehend $item, int $min = 0, ?int $max = null): Parser => new Repeat(
                $item->parser(),
                $min,
                $max,
            ),
            'some' => static fn(Comprehend $item): Comprehend => $item->repeat(1),
            'many' => static fn(Comprehend $item): Comprehend => $item->repeat(),
            'maybe' => static fn(Comprehend $item): Comprehend => $item->repeat(0, 1),
            'named' => static fn(
                Comprehend $item,
                string|int|callable $name,
                ?string $group = null,
                bool $leaf = false,
            ): Parser => $item->parser()->name($name, $group, $leaf),
            'tag' => static fn(Comprehend $item, string ...$tags): Parser => $item->parser()->tag(...$tags),
            'mapvalue' => static fn(Comprehend $item, callable $mapper): Parser => $item->parser()->mapValue($mapper),
            'guard' => static fn(Comprehend $item, callable $predicate): Parser => $item->parser()->guard($predicate),
            'validatesemantic' => static fn(
                Comprehend $item,
                callable $validator,
            ): Parser => $item->parser()->validateSemantic($validator),
            'oncommit' => static fn(Comprehend $item, callable $effect): Parser => $item->parser()->onCommit($effect),
            'setresult' => static fn(
                Comprehend $item,
                ?string $key = null,
                mixed $value = null,
            ): Parser => $item->parser()->setResult($key, $value),
            'concatresult' => static fn(
                Comprehend $item,
                ?string $key = null,
                mixed $value = null,
            ): Parser => $item->parser()->concatResult($key, $value),
            'pushresult' => static fn(
                Comprehend $item,
                ?string $key = null,
                mixed $value = null,
            ): Parser => $item->parser()->pushResult($key, $value),
            'diagnosticrule' => static fn(Comprehend $item, string $name): Parser => $item->parser()->diagnosticRule(
                $name,
            ),
            'casesensitive' => static fn(Comprehend $item, bool $sensitivity = true): Parser => new CaseSensitive(
                $sensitivity,
                $item->parser(),
            ),
            'prefer' => static fn(Comprehend $item, string $preference): Parser => new Prefer(
                $preference,
                $item->parser(),
            ),
            'spacing' => static fn(Comprehend $item, Comprehend|Parser|null $trivia): Parser => new Space(
                $trivia === null ? null : self::parser($trivia),
                $item->parser(),
            ),
            'lexeme' => static fn(Comprehend $item): Parser => $item->parser()->lexeme(),
            'trivia' => static fn(Comprehend $item): Parser => $item->parser()->trivia(),
            default => null,
        };
    }

    /** Inherited choice policies are associative; explicit policies remain nested. */
    private static function choice(array $items): Choice
    {
        $pending = array_reverse(array_map(self::parser(...), $items));
        $children = [];
        $expanded = new \SplObjectStorage();
        while ($pending !== []) {
            $parser = array_pop($pending);
            if ($parser instanceof Choice && $parser->canFlatten() && !isset($expanded[$parser])) {
                $alternatives = iterator_to_array($parser, false);
                if ($alternatives !== []) {
                    $expanded[$parser] = true;
                    array_push($pending, ...array_reverse($alternatives));
                    continue;
                }
            }
            $children[] = $parser;
        }
        return new Choice(...$children);
    }

    /** Flatten only prefixes: other positions can introduce extra spacing boundaries. */
    private static function sequence(array $items): Sequence
    {
        $parsers = array_map(self::parser(...), $items);
        $seen = new \SplObjectStorage();
        while (($parsers[0] ?? null) instanceof Sequence && $parsers[0]->canFlattenPrefix()) {
            $prefix = $parsers[0];
            if (isset($seen[$prefix])) {
                break;
            }
            $seen[$prefix] = true;
            $children = iterator_to_array($prefix, false);
            if ($children === []) {
                break;
            }
            $parsers = [...$children, ...array_slice($parsers, 1)];
        }
        return new Sequence(...$parsers);
    }

    /** Detach caller-owned graphs before composing syntax. */
    public static function parser(Comprehend|Parser|string $parser): Parser
    {
        if (is_string($parser)) {
            return new Text($parser);
        }
        return $parser instanceof Comprehend ? $parser->parser() : (new GraphSnapshot(false))->copy($parser);
    }

    public static function builtinNames(): array
    {
        return [...self::BUILTINS, ...self::CONTINUATIONS, ...array_keys(self::ALIASES)];
    }

    public function define(string $name, callable $factory): self
    {
        $name = self::validateName($name);
        return new self([...$this->starts, $name => Closure::fromCallable($factory)], $this->continuations);
    }

    public function defineContinuation(string $name, callable $factory): self
    {
        $name = self::validateName($name);
        return new self($this->starts, [...$this->continuations, $name => Closure::fromCallable($factory)]);
    }

    private static function validateName(string $name): string
    {
        $name = strtolower($name);
        if (!preg_match('/\A[a-z][a-z0-9_]*\z/', $name)) {
            throw new InvalidArgumentException('Syntax names must be method identifiers');
        }
        if (
            in_array($name, self::builtinNames(), true)
            || method_exists(Comprehend::class, $name)
            || method_exists(Syntax::class, $name)
        ) {
            throw new InvalidArgumentException('Syntax name is reserved: ' . $name);
        }
        return $name;
    }

    public function start(string $name): ?Closure
    {
        return self::builtinStart($name) ?? $this->starts[self::canonicalName($name)] ?? null;
    }

    public function continuation(string $name): ?Closure
    {
        return self::builtinContinuation($name) ?? $this->continuations[self::canonicalName($name)] ?? null;
    }

    public function names(): array
    {
        return array_values(array_unique([
            ...self::builtinNames(),
            ...array_keys($this->starts),
            ...array_keys($this->continuations),
        ]));
    }
}
