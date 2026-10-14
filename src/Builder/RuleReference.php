<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Builder;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Match\AbstractMatch;
/** Explicit deferred lookup; graph-wide resolution/freezing is Phase 3 work. */
final class RuleReference extends Parser
{
    public function __construct(private readonly AbstractRuleset $rules, private readonly string $rule, private readonly array $arguments = [])
    {
        $this->name($rule, $rules->groupName());
        $this->diagnosticRule($rule);
    }
    protected function recognize(Source $source, int $offset, ParseSession $session): AbstractMatch
    {
        return $this->rules->resolveRule($this->rule, $this->arguments)->recognizeInSession($source, $offset, $session);
    }
    public function __toString(): string
    {
        return $this->rule;
    }
}
