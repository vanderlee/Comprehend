<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Closure;
use InvalidArgumentException;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Syntax;

/** Coordinates built-in dispatch and immutable custom vocabularies. */
final readonly class Catalog
{
    private const BUILTINS = [
        'text' => ['makeText'], 'regex' => ['makeRegex'], 'char' => ['makeChar'],
        'set' => ['makeSet'], 'range' => ['makeRange'], 'any' => ['makeAny'],
        'nothing' => ['makeNothing'], 'start' => ['makeStart'], 'end' => ['makeEnd'],
        'integer' => ['makeInteger'], 'binary' => ['makeBinary'], 'octal' => ['makeOctal'],
        'hexadecimal' => ['makeHexadecimal'], 'octet' => ['makeOctet'],
        'literalwindow' => ['makeLiteralWindow'], 'sequence' => ['makeSequence'],
        'choice' => ['makeChoice'], 'or' => ['makeChoice', 'chooseExpression'],
        'first' => ['makeFirst'], 'longest' => ['makeLongest'], 'shortest' => ['makeShortest'],
        'all' => ['makeAll'], 'except' => ['makeExcept'], 'not' => ['makeNot'], 'ref' => ['makeRef'],
        'repeat' => ['repeatOf', 'repeatExpression'], 'some' => ['someOf', 'fluentSome'],
        'many' => ['manyOf', 'fluentMany'], 'maybe' => ['maybeOf', 'fluentMaybe'],
    ];

    private const ALIASES = [
        'firstof' => 'first', 'longestof' => 'longest', 'shortestof' => 'shortest',
        'plus' => 'some', 'star' => 'many', 'kleene' => 'many', 'optional' => 'maybe',
    ];

    public function __construct(private array $starts = [], private array $continuations = []) {}

    public static function canonicalName(string $name): string
    {
        $name = strtolower($name);
        return self::ALIASES[$name] ?? $name;
    }

    public static function startMethod(string $name): ?string
    {
        return self::BUILTINS[self::canonicalName($name)][0] ?? null;
    }

    public static function continuationMethod(string $name): ?string
    {
        return self::BUILTINS[self::canonicalName($name)][1] ?? null;
    }

    public static function builtinNames(): array
    {
        return [...array_keys(self::BUILTINS), ...array_keys(self::ALIASES)];
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
        return $this->starts[self::canonicalName($name)] ?? null;
    }

    public function continuation(string $name): ?Closure
    {
        return $this->continuations[self::canonicalName($name)] ?? null;
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
