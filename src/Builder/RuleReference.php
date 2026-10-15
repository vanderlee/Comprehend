<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Match\AbstractMatch;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;

/** Explicit deferred lookup, bound to a detached target when frozen. */
final class RuleReference extends Parser
{
    private ?Parser $target = null;
    private readonly object $scopeIdentity;

    public function __construct(
        private ?AbstractRuleset $rules,
        private readonly string $rule,
        private readonly array $arguments = [],
    ) {
        $this->scopeIdentity = $rules->graphIdentity();
        $this->name($rule, $rules->groupName());
        $this->diagnosticRule($rule);
    }

    /** @internal Parameter identities avoid serializing user objects. */
    public function graphKey(): array
    {
        return [$this->scopeIdentity, $this->rule, $this->arguments];
    }

    public function bindSnapshotTarget(Parser $target): void
    {
        $this->target = $target;
        $this->rules = null;
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
        return $this->target ?? $this->rules->resolveRule($this->rule, $this->arguments, $fresh);
    }
}
