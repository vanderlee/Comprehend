<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Runtime\ParseSession;

/** A local case policy; null inherits the session policy. */
trait CaseSensitiveTrait
{
    private ?bool $caseSensitivity = null;

    /**
     * @param bool|null $preference
     *
     * @return $this
     */
    public function setCaseSensitivity(?bool $preference): static
    {
        $this->caseSensitivity = $preference;

        return $this;
    }

    public function caseSensitive(): static
    {
        $this->caseSensitivity = true;

        return $this;
    }

    public function caseInsensitive(): static
    {
        $this->caseSensitivity = false;

        return $this;
    }

    private function pushCaseSensitivityToContext(ParseSession $context): void
    {
        if ($this->caseSensitivity !== null) {
            $context->pushCaseSensitivity($this->caseSensitivity);
        }
    }

    private function popCaseSensitivityFromContext(ParseSession $context): void
    {
        if ($this->caseSensitivity !== null) {
            $context->popCaseSensitivity();
        }
    }
}
