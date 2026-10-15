<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Runtime\ParseSession;

/** A local choice preference; null inherits the session policy. */
trait PreferTrait
{
    private ?string $preference = null;

    /**
     * @param string|null $preference
     *
     * @return $this
     */
    public function setPreference(?string $preference): static
    {
        $this->preference = $preference;

        return $this;
    }

    public function preferShortest(): static
    {
        $this->preference = Prefer::SHORTEST;

        return $this;
    }

    public function preferLongest(): static
    {
        $this->preference = Prefer::LONGEST;

        return $this;
    }

    public function preferFirst(): static
    {
        $this->preference = Prefer::FIRST;

        return $this;
    }

    private function pushPreferenceToContext(ParseSession $context): void
    {
        if ($this->preference) {
            $context->pushPreference($this->preference);
        }
    }

    private function popPreferenceFromContext(ParseSession $context): void
    {
        if ($this->preference) {
            $context->popPreference();
        }
    }
}
