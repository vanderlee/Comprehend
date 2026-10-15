<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;

/**
 * Adds an explicit intervening-trivia policy to Sequence and Repeat. Pushes the configured
 * spacer into ParseSession; null disables spacing and an unset policy inherits its scope.
 */
trait SpacingTrait
{
    private ?Parser $spacer = null;
    private bool $hasSpacingPolicy = false;

    public function spacing(?Parser $trivia): static
    {
        $this->spacer = $trivia;
        $this->hasSpacingPolicy = true;

        return $this;
    }

    private function pushSpacer(ParseSession $context): void
    {
        if ($this->hasSpacingPolicy) {
            $context->pushSpacer($this->spacer);
        }
    }

    private function popSpacer(ParseSession $context): void
    {
        if ($this->hasSpacingPolicy) {
            $context->popSpacer();
        }
    }
}
