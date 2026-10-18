<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Runtime\ParseSession;

/**
 * Adds an explicit intervening-trivia policy to composite parsers. Pushes the configured
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

    /** Whether this parser establishes a local spacing scope, including an explicit null policy. */
    public function hasExplicitSpacing(): bool
    {
        return $this->hasSpacingPolicy;
    }

    private function copySpacer(GraphSnapshot $context): void
    {
        if ($this->spacer !== null) {
            $this->spacer = $context->copy($this->spacer);
        }
    }

    /**
     * @param list<Parser> $children
     * @return list<Parser>
     */
    private function childrenWithSpacer(array $children): array
    {
        if ($this->spacer !== null) {
            $children[] = $this->spacer;
        }

        return $children;
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
