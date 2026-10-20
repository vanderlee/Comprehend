<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Closure;
use InvalidArgumentException;
use SplObjectStorage;
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
use Vanderlee\Comprehend\Parser\Structure\SeparatedList;
use Vanderlee\Comprehend\Parser\Structure\ValueSequence;
use Vanderlee\Comprehend\Parser\Terminal\Any;
use Vanderlee\Comprehend\Parser\Terminal\Char;
use Vanderlee\Comprehend\Parser\Terminal\End;
use Vanderlee\Comprehend\Parser\Terminal\Nothing;
use Vanderlee\Comprehend\Parser\Terminal\Range;
use Vanderlee\Comprehend\Parser\Terminal\Regex;
use Vanderlee\Comprehend\Parser\Terminal\Set;
use Vanderlee\Comprehend\Parser\Terminal\Start;
use Vanderlee\Comprehend\Parser\Terminal\Text;
use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Syntax;

/**
 * Maps built-in and custom syntax names to typed grammar factories and fluent operations.
 * Supports immutable Syntax vocabularies and Comprehend dispatch without adding a recognition
 * runtime.
 */
final readonly class Catalog
{
    private const RULES = [
        'text',
        'regex',
        'char',
        'set',
        'range',
        'any',
        'nothing',
        'start',
        'end',
        'literalwindow',
        'separatedby',
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

    private const FLUENTS = [
        'thenignore',
        'ignorethen',
        'between',
        'then',
        'prepend',
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
        'label',
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

    /**
     * @param array<string, Closure> $rules
     * @param array<string, Closure> $fluents
     */
    public function __construct(private array $rules = [], private array $fluents = []) {}

    public static function canonicalName(string $name): string
    {
        $name = strtolower($name);

        return self::ALIASES[$name] ?? $name;
    }

    public static function builtinRule(string $name): ?Closure
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
            'literalwindow' => static fn(string $literal): Parser => Regex::literalWindow($literal),
            'separatedby' => static fn(
                Comprehend|Parser|string $item,
                Comprehend|Parser|string $separator,
                int $min = 0,
                ?int $max = null,
                bool $allowLeading = false,
                bool $allowTrailing = false,
            ): Parser => new SeparatedList(
                self::parser($item),
                self::parser($separator),
                $min,
                $max,
                $allowLeading,
                $allowTrailing,
            ),
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
            ): Comprehend => self::repeat($parser, $min, $max),

            'some' => static fn(Comprehend|Parser|string $parser): Comprehend => self::repeat($parser, 1),
            'many' => static fn(Comprehend|Parser|string $parser): Comprehend => self::repeat($parser),
            'maybe' => static fn(Comprehend|Parser|string $parser): Comprehend => self::repeat($parser, 0, 1),
            default => null,
        };
    }

    public static function builtinFluent(string $name): ?Closure
    {
        return match (self::canonicalName($name)) {
            'thenignore' => static fn(Comprehend $item, Comprehend|Parser|string $next): Parser => new ValueSequence(
                0,
                $item->parser(),
                self::parser($next),
            ),
            'ignorethen' => static fn(Comprehend $item, Comprehend|Parser|string $next): Parser => new ValueSequence(
                1,
                $item->parser(),
                self::parser($next),
            ),
            'between' => static fn(
                Comprehend $item,
                Comprehend|Parser|string $open,
                Comprehend|Parser|string $close,
            ): Parser => new ValueSequence(1, self::parser($open), $item->parser(), self::parser($close)),
            'separatedby' => static fn(
                Comprehend $item,
                Comprehend|Parser|string $separator,
                int $min = 0,
                ?int $max = null,
                bool $allowLeading = false,
                bool $allowTrailing = false,
            ): Parser => new SeparatedList(
                $item->parser(),
                self::parser($separator),
                $min,
                $max,
                $allowLeading,
                $allowTrailing,
            ),
            'then' => static fn(
                Comprehend $item,
                Comprehend|Parser|string ...$next,
            ): Comprehend|Parser => $next === [] ? $item : self::sequence([$item, ...$next]),
            'prepend' => static fn(
                Comprehend $item,
                Comprehend|Parser|string ...$before,
            ): Comprehend|Parser => $before === [] ? $item : self::sequence([...array_values($before), $item]),
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
            'label' => static fn(Comprehend $item, string $label): Parser => $item->parser()->label($label),
            'casesensitive' => static fn(Comprehend $item, bool $sensitivity = true): Parser => new CaseSensitive(
                $sensitivity,
                $item->parser(),
            ),
            'prefer' => static fn(Comprehend $item, ChoicePolicy $preference): Parser => new Prefer(
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

    /**
     * @param Comprehend|Parser|string $parser
     * @param int $min
     * @param int|null $max
     * @return Comprehend
     */
    private static function repeat(Comprehend|Parser|string $parser, int $min = 0, ?int $max = null): Comprehend
    {
        // Enter fluent dispatch on an expression; static dispatch would call this factory again.
        // Retain an existing expression's vocabulary for subsequent custom syntax calls.
        $expression = Comprehend::of(is_string($parser) ? new Text($parser) : $parser);

        return $expression->repeat($min, $max);
    }

    /**
     * Inherited choice policies are associative; explicit policies remain nested.
     * @param array<array-key, Comprehend|Parser|string> $items
     * @return Choice
     */
    private static function choice(array $items): Choice
    {
        $parsers = array_map(self::parser(...), $items);

        return new Choice(...self::flattenChoices($parsers));
    }

    /**
     * @param array<array-key, Parser> $parsers Detached operands in insertion order.
     * @return list<Parser>
     */
    private static function flattenChoices(array $parsers): array
    {
        $pending = array_reverse($parsers);
        $children = [];
        $expanded = new SplObjectStorage();

        while ($pending !== []) {
            $parser = array_pop($pending);
            if (
                !$parser instanceof Choice
                || !$parser->canFlatten()
                || isset($expanded[$parser])
            ) {
                $children[] = $parser;
                continue;
            }

            $alternatives = iterator_to_array($parser, false);
            if ($alternatives === []) {
                $children[] = $parser;
                continue;
            }

            $expanded[$parser] = true;

            // The stack visits alternatives in their original order, including ties.
            array_push($pending, ...array_reverse($alternatives));
        }

        return $children;
    }

    /**
     * Flatten only prefixes: other positions can introduce extra spacing boundaries.
     * @param array<array-key, Comprehend|Parser|string> $items
     */
    private static function sequence(array $items): Sequence
    {
        $parsers = array_values(array_map(self::parser(...), $items));

        return new Sequence(...self::flattenSequencePrefix($parsers));
    }

    /**
     * @param list<Parser> $parsers Detached operands in insertion order.
     * @return list<Parser>
     */
    private static function flattenSequencePrefix(array $parsers): array
    {
        $seen = new SplObjectStorage();
        while (
            ($parsers[0] ?? null) instanceof Sequence
            && $parsers[0]->canFlattenPrefix()
        ) {
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

        return $parsers;
    }

    /**
     * Detach caller-owned graphs before composing syntax.
     *
     * @param Comprehend|Parser|string $parser
     * @return Parser
     */
    public static function parser(Comprehend|Parser|string $parser): Parser
    {
        if (is_string($parser)) {
            return new Text($parser);
        }

        if ($parser instanceof Comprehend) {
            return $parser->parser();
        }

        return (new GraphSnapshot(false))->copy($parser);
    }

    /**
     * @return list<string>
     */
    public static function builtinNames(): array
    {
        return [
            ...self::RULES,
            ...self::FLUENTS,
            ...array_keys(self::ALIASES),
        ];
    }

    /**
     * @param string $name
     * @param callable $factory
     * @return self
     */
    public function defineRule(string $name, callable $factory): self
    {
        $name = self::validateName($name);

        return new self(
            [
                ...$this->rules,
                $name => Closure::fromCallable($factory)],
            $this->fluents,
        );
    }

    /**
     * @param string $name
     * @param callable $factory
     * @return self
     */
    public function defineFluent(string $name, callable $factory): self
    {
        $name = self::validateName($name);

        return new self($this->rules, [
            ...$this->fluents,
            $name => Closure::fromCallable($factory),
        ]);
    }

    /**
     * @param string $name
     * @return string
     */
    private static function validateName(string $name): string
    {
        $name = strtolower($name);
        if (!preg_match('/\A[a-z][a-z0-9_]*\z/', $name)) {
            throw new InvalidArgumentException('Syntax names must be method identifiers');
        }

        // Keep all declared facade/vocabulary names reserved, including private helpers.
        if (
            in_array($name, self::builtinNames(), true)
            || method_exists(Comprehend::class, $name)
            || method_exists(Syntax::class, $name)
        ) {
            throw new InvalidArgumentException('Syntax name is reserved: ' . $name);
        }

        return $name;
    }

    /**
     * @param string $name
     * @return Closure|null
     */
    public function rule(string $name): ?Closure
    {
        $builtin = self::builtinRule($name);
        if ($builtin !== null) {
            return $builtin;
        }

        return $this->rules[self::canonicalName($name)] ?? ExtensionLoader::defaults()->rule($name);
    }

    /**
     * @param string $name
     * @return Closure|null
     */
    public function fluent(string $name): ?Closure
    {
        $builtin = self::builtinFluent($name);
        if ($builtin !== null) {
            return $builtin;
        }

        return $this->fluents[self::canonicalName($name)] ?? ExtensionLoader::defaults()->fluent($name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique([
            ...self::builtinNames(),
            ...array_keys($this->rules),
            ...array_keys($this->fluents),
            ...ExtensionLoader::defaults()->names(),
        ]));
    }
}
