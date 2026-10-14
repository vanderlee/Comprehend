<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use Closure;
use InvalidArgumentException;

/** An immutable, explicitly scoped vocabulary of syntax factories. */
final readonly class Syntax
{
    private function __construct(private array $factories = [], private array $continuations = []) {}

    public static function create(): self
    {
        return new self();
    }

    /** Return a new vocabulary; existing expressions keep their original scope. */
    public function define(string $name, callable $factory): self
    {
        $name = strtolower($name);
        self::validateName($name);
        return new self([...$this->factories, $name => Closure::fromCallable($factory)], $this->continuations);
    }

    /** Continuation callbacks receive the current facade before user arguments. */
    public function defineContinuation(string $name, callable $factory): self
    {
        $name = strtolower($name);
        self::validateName($name);
        return new self($this->factories, [...$this->continuations, $name => Closure::fromCallable($factory)]);
    }

    private static function validateName(string $name): void
    {
        if (!preg_match('/\A[a-z][a-z0-9_]*\z/', $name)) {
            throw new InvalidArgumentException('Syntax names must be method identifiers');
        }
        if (in_array($name, Comprehend::syntaxNames(), true)
            || method_exists(Comprehend::class, $name) || method_exists(self::class, $name)) {
            throw new InvalidArgumentException('Syntax name is reserved: ' . $name);
        }
    }

    public function __call(string $name, array $arguments): Comprehend
    {
        return Comprehend::callSyntax($name, $arguments, $this);
    }

    public function call(string $name, mixed ...$arguments): Comprehend
    {
        return Comprehend::callSyntax($name, $arguments, $this);
    }

    public function names(): array
    {
        return array_values(array_unique([...Comprehend::syntaxNames(), ...array_keys($this->factories), ...array_keys($this->continuations)]));
    }

    /** @internal Used by the facade's authoring dispatch, never by recognition. */
    public function factory(string $name): ?Closure
    {
        return $this->factories[strtolower($name)] ?? null;
    }

    /** @internal Used only while constructing a fluent expression. */
    public function continuation(string $name): ?Closure
    {
        return $this->continuations[strtolower($name)] ?? null;
    }
}
