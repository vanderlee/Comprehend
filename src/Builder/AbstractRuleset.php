<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use InvalidArgumentException;
use LogicException;
use Vanderlee\Comprehend\Comprehend;
use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/**
 * Organizes named rules and parameterized factories on the shared Parser runtime. Resolves
 * RuleReference targets and wraps Definition instances while keeping registrations local to
 * each ruleset.
 */
abstract class AbstractRuleset extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    public const ROOT = 'ROOT';
    /**
     * @var string|null
     */
    protected static $name = null;
    /** @var array<string, Definition|Parser|callable> */
    protected array $instanceRules = [];
    /**
     * @var array<string, Parser>
     */
    private array $implementations = [];
    private ?object $graphIdentity = null;
    private ?string $groupName = null;

    /** @param array<string, Comprehend|Parser|Definition|callable> $rules */
    public function __construct(array $rules = [], ?string $name = null)
    {
        $this->groupName = $name ?? static::$name;
        foreach ($rules as $key => $rule) {
            $this->define($key, $rule);
        }
    }

    /** Facade values become detached parser graphs when registered. */
    public function define(string $name, Comprehend|Parser|Definition|callable $definition): static
    {
        if ($name === '') {
            throw new InvalidArgumentException('Rule name cannot be empty');
        }
        $this->instanceRules[$name] = $definition instanceof Comprehend ? $definition->parser() : $definition;
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

    public function root(string|Comprehend|Parser $rule): static
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

    /** Fluent deferred rule lookup; use ref() for Parser-only consumers. */
    public function rule(string $name, mixed ...$arguments): Comprehend
    {
        return Comprehend::ref($this, $name, ...$arguments);
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return [$this->resolveRule(self::ROOT)];
    }

    protected function analysisTarget(): Parser
    {
        return $this->resolveRule(self::ROOT);
    }

    public function __toString(): string
    {
        return static::class;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        $match = $this->resolveRule(self::ROOT)->recognizeInSession($source, $byteOffset, $session);
        if (!$match instanceof Success) {
            return $this->failure($source, $byteOffset, $match->length);
        }
        $success = $this->success($source, $byteOffset, $match->length, $match);
        $success->inheritValueFrom($match);

        return $success;
    }

    /**
     * @internal Resolve on recognition, never guess a literal or class name.
     * @param array<array-key, mixed> $arguments
     */
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
            $definition instanceof Definition => $definition->implementation(...$arguments),
            $definition instanceof Parser => clone $definition,
            default => $definition(...$arguments),
        };
        if ($parser instanceof Comprehend) {
            $parser = $parser->parser();
        }
        if (!$parser instanceof Parser) {
            throw new LogicException('Rule factory must return Comprehend or Parser: ' . $name);
        }
        $parser->diagnosticRule($name);
        if (!$fresh && $arguments === []) {
            $this->implementations[$name] = $parser;
        }

        return $parser;
    }
}
