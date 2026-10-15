<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use Vanderlee\Comprehend\Runtime\ParseSession;

/**
 * Adds a local choice-selection policy to composite parsers such as Choice. Pushes an explicit
 * preference into ParseSession or inherits the enclosing policy when unset.
 */
trait PreferTrait
{
    private ?ChoicePolicy $preference = null;

    /**
     * @param ChoicePolicy|null $preference
     *
     * @return $this
     */
    public function setPreference(?ChoicePolicy $preference): static
    {
        $this->preference = $preference;

        return $this;
    }

    public function preferShortest(): static
    {
        $this->preference = ChoicePolicy::SHORTEST;

        return $this;
    }

    public function preferLongest(): static
    {
        $this->preference = ChoicePolicy::LONGEST;

        return $this;
    }

    public function preferFirst(): static
    {
        $this->preference = ChoicePolicy::FIRST;

        return $this;
    }

    private function pushPreferenceToContext(ParseSession $context): void
    {
        if ($this->preference !== null) {
            $context->pushPreference($this->preference);
        }
    }

    private function popPreferenceFromContext(ParseSession $context): void
    {
        if ($this->preference !== null) {
            $context->popPreference();
        }
    }
}
