<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend;

use Closure;
use Vanderlee\Comprehend\Syntax\Catalog;
use Vanderlee\Comprehend\Syntax\SyntaxExtension;

/**
 * Defines an immutable vocabulary of custom grammar factories and fluent operations. Delegates
 * authoring calls through Catalog to Comprehend so extensions use the same parser runtime.
 */
final class Syntax
{
    private function __construct(private readonly Catalog $catalog) {}

    public static function create(): self
    {
        return new self(new Catalog());
    }

    /** Existing expressions retain their original catalog. */
    public function defineRule(string $name, callable $factory): self
    {
        return new self($this->catalog->defineRule($name, $factory));
    }

    /** Install rule then fluent factories; the original vocabulary survives installation errors. */
    public function extend(SyntaxExtension $extension): self
    {
        $syntax = $this;
        foreach ($extension->rules() as $name => $factory) {
            $syntax = $syntax->defineRule($name, $factory);
        }
        foreach ($extension->fluents() as $name => $factory) {
            $syntax = $syntax->defineFluent($name, $factory);
        }

        return $syntax;
    }

    /** Callbacks receive the current facade before user arguments. */
    public function defineFluent(string $name, callable $factory): self
    {
        return new self($this->catalog->defineFluent($name, $factory));
    }

    /**
     * Start an expression through static factory lookup, including when a fluent shares its name.
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $name, array $arguments): Comprehend
    {
        return Comprehend::callSyntax($name, $arguments, $this);
    }

    /** Explicit-name equivalent of magic factory dispatch; argument keys are retained. */
    public function call(string $name, mixed ...$arguments): Comprehend
    {
        return Comprehend::callSyntax($name, $arguments, $this);
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->catalog->names();
    }

    /** @internal Authoring dispatch only. */
    public function rule(string $name): ?Closure
    {
        return $this->catalog->rule($name);
    }

    /** @internal Authoring dispatch only. */
    public function fluent(string $name): ?Closure
    {
        return $this->catalog->fluent($name);
    }
}
