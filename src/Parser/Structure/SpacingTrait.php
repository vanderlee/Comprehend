<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Builder\GraphSnapshot;
use Vanderlee\Comprehend\Parser\Parser;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Source\Source;
use Vanderlee\Comprehend\Tree\ConcreteNode;

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

    /** Prepared implementations may return stateless syntax while ordinary/custom loops retain matches. */
    protected function skipSpacing(
        Source $source,
        int $offset,
        ParseSession $session,
        Success|ConcreteNode|null &$syntax,
    ): int|false {
        $syntax = null;

        return $session->skipSpacing($source, $offset, $syntax);
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
