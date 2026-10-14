<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Named rules and parameterized factories, directly on the source runtime. */
abstract class AbstractRuleset extends Parser
{
    public const ROOT = 'ROOT';
    protected static $name = null;
    /** @var array<string, Definition|Parser|callable> */
    protected array $instanceRules = [];
    private array $implementations = [];
    private ?object $graphIdentity = null;
    private ?string $groupName = null;

    public function __construct(array $rules = [], ?string $name = null)
    {
        $this->groupName = $name ?? static::$name;
        foreach ($rules as $key => $rule) {
            $this->define($key, $rule);
        }
    }

    public function define(string $name, Parser|Definition|callable $definition): static
    {
        if ($name === '') {
            throw new InvalidArgumentException('Rule name cannot be empty');
        }
        $this->instanceRules[$name] = $definition;
        // Explicit redefinition affects future recognitions; existing results
        // retain their own nodes, values, validators and effects.
        $this->implementations = [];
        return $this;
    }

    public function graphIdentity(): object
    {
        return $this->graphIdentity ??= new \stdClass();
    }

    public function groupName(): ?string
    {
        return $this->groupName ?? static::$name;
    }

    public function defined(string $name): bool
    {
        return isset($this->instanceRules[$name]);
    }

    public function root(string|Parser $rule): static
    {
        return $this->define(self::ROOT, is_string($rule) ? $this->ref($rule) : $rule);
    }

    public function ref(string $name, mixed ...$arguments): RuleReference
    {
        return new RuleReference($this, $name, $arguments);
    }

    /** @internal A snapshot retains only its reachable, resolved root. */
    public function bindSnapshotRoot(Parser $root): void
    {
        $this->instanceRules = [self::ROOT => $root];
        $this->implementations = [self::ROOT => $root];
    }

    public function undefine(string $name): void
    {
        unset($this->instanceRules[$name]);
        $this->implementations = [];
    }

    /** Explicit deferred rule lookup permits construction in any order. */
    public function rule(string $name, mixed ...$arguments): Parser
    {
        return $this->ref($name, ...$arguments);
    }

    public function __toString(): string
    {
        return static::class;
    }

    protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        $match = $this->resolveRule(self::ROOT)->recognizeInSession($source, $offset, $session);
        if (!$match instanceof Success) {
            return $this->failure($source, $offset, $match->length);
        }
        $success = $this->success($source, $offset, $match->length, $match);
        $success->inheritValueFrom($match);
        return $success;
    }

    /** @internal Resolve on recognition, never guess a literal or class name. */
    public function resolveRule(string $name, array $arguments = [], bool $fresh = false): Parser
    {
        if (!isset($this->instanceRules[$name])) {
            throw new LogicException('Undefined grammar rule: ' . $name);
        }
        // Factory arguments can contain arbitrary objects; avoid serialization
        // or shared caches for parameterized implementations.
        if (!$fresh && $arguments === [] && isset($this->implementations[$name])) {
            return $this->implementations[$name];
        }
        $definition = $this->instanceRules[$name];
        if ($definition instanceof Parser && $arguments !== []) {
            throw new LogicException('Only rule factories accept arguments: ' . $name);
        }
        $parser = match (true) {
            $definition instanceof Definition => $definition->build(...$arguments),
            $definition instanceof Parser => clone $definition,
            default => $definition(...$arguments),
        };
        if (!$parser instanceof Parser) {
            throw new LogicException('Rule factory must return Parser: ' . $name);
        }
        $parser->diagnosticRule($name);
        if (!$fresh && $arguments === []) {
            $this->implementations[$name] = $parser;
        }
        return $parser;
    }
}
