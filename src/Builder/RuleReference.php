<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use LogicException;

/**
 * Represents a deferred named rule lookup within an AbstractRuleset. Supports forward and
 * recursive references and binds to a detached target when GraphSnapshot freezes the grammar.
 */
final class RuleReference extends Parser implements AnalyzableParser
{
    use ForwardingAnalysisTrait;

    private ?Parser $target = null;
    private readonly object $scopeIdentity;
    private ?AbstractRuleset $rules;

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(
        AbstractRuleset $rules,
        private readonly string $rule,
        private readonly array $arguments = [],
    ) {
        $this->rules = $rules;
        $this->scopeIdentity = $rules->graphIdentity();
        $this->name($rule, $rules->groupName());
        $this->diagnosticRule($rule);
    }

    /**
     * @internal Parameter identities avoid serializing user objects.
     * @return array{object, string, array<array-key, mixed>}
     */
    public function graphKey(): array
    {
        return [$this->scopeIdentity, $this->rule, $this->arguments];
    }

    public function bindSnapshotTarget(Parser $target): void
    {
        $this->target = $target;
        $this->rules = null;
    }

    public function snapshot(GraphSnapshot $context): Parser
    {
        $copy = clone $this;
        if (!$context->resolvesReferences()) {
            // Authoring copies retain their builder so forward definitions remain visible.
            $context->registerCopy($this, $copy);

            return $copy;
        }

        // Register before resolving the factory so recursive references find this destination.
        $shared = $context->registerIdentity($this, $copy, [self::class, $this->graphKey()]);
        if ($shared !== $copy) {
            return $shared;
        }

        $copy->bindSnapshotTarget($context->copy($this->graphTarget(true)));

        return $copy;
    }

    /** @return list<Parser> */
    public function children(): array
    {
        return [$this->graphTarget()];
    }

    protected function analysisTarget(): Parser
    {
        return $this->graphTarget();
    }

    public function __toString(): string
    {
        return $this->rule;
    }

    protected function recognize(Source $source, int $byteOffset, ParseSession $session): AbstractMatch
    {
        return $this->graphTarget()->recognizeInSession($source, $byteOffset, $session);
    }

    /** @internal Resolve factories once for each parameterized reference in a snapshot. */
    public function graphTarget(bool $fresh = false): Parser
    {
        if ($this->target !== null) {
            return $this->target;
        }
        if ($this->rules === null) {
            throw new LogicException('Rule reference requires a ruleset or snapshot target');
        }

        return $this->rules->resolveRule($this->rule, $this->arguments, $fresh);
    }
}
