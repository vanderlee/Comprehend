<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Structure;

use Vanderlee\Comprehend\Runtime\ParseSession;
use Vanderlee\Comprehend\Directive\Prefer;
use Vanderlee\Comprehend\Parser\Parser;
/**
 * Classes implementing this can scan.
 *
 * @author Martijn
 */
trait PreferTrait
{
    /**
     * Parser used for scanning the text.
     *
     * @var Parser
     */
    private $preference = null;
    private function pushPreferenceToContext(ParseSession $context)
    {
        if ($this->preference) {
            $context->pushPreference($this->preference);
        }
    }
    private function popPreferenceFromContext(ParseSession $context)
    {
        if ($this->preference) {
            $context->popPreference();
        }
    }
    /**
     * @param string $preference
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
}
