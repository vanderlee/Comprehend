<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Parser\Parser;
/** Explicit scoped policy for intervening trivia; null disables it. */
trait SpacingTrait
{
    private ?Parser $spacer = null;
    private bool $hasSpacingPolicy = false;
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
    public function spacing(?Parser $trivia): static
    {
        $this->spacer = $trivia;
        $this->hasSpacingPolicy = true;
        return $this;
    }
}
