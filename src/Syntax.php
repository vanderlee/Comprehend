<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use Closure;
use Vanderlee\Comprehend\Syntax\Catalog;

/** An immutable vocabulary of start and continuation callbacks. */
final class Syntax
{
    private function __construct(private readonly Catalog $catalog) {}

    public static function create(): self
    {
        return new self(new Catalog());
    }

    /** Existing expressions retain their original catalog. */
    public function define(string $name, callable $factory): self
    {
        return new self($this->catalog->define($name, $factory));
    }

    /** Callbacks receive the current facade before user arguments. */
    public function defineContinuation(string $name, callable $factory): self
    {
        return new self($this->catalog->defineContinuation($name, $factory));
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
        return $this->catalog->names();
    }

    /** @internal Authoring dispatch only. */
    public function factory(string $name): ?Closure
    {
        return $this->catalog->start($name);
    }

    /** @internal Authoring dispatch only. */
    public function continuation(string $name): ?Closure
    {
        return $this->catalog->continuation($name);
    }
}
