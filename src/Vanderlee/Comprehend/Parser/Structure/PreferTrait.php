<?php

namespace vanderlee\comprehend\parser\structure;

use vanderlee\comprehend\core\Context;
use vanderlee\comprehend\directive\Prefer;
use vanderlee\comprehend\parser\Parser;

/**
 * Classes implementing this can scan
 *
 * @author Martijn
 */
trait PreferTrait
{

    /**
     * Parser used for scanning the text
     * @var Parser
     */
    private $preference = null;

    private function pushPreferenceToContext(Context $context)
    {
        if ($this->preference) {
            $context->pushPreference($this->preference);
        }
    }

    private function popPreferenceFromContext(Context $context)
    {
        if ($this->preference) {
            $context->popPreference();
        }
    }

    public function setPreference(string $preference)
    {
        $this->preference = $preference;

        return $this;
    }

    public function preferShortest()
    {
        $this->preference = Prefer::SHORTEST;

        return $this;
    }

    public function preferLongest()
    {
        $this->preference = Prefer::LONGEST;

        return $this;
    }

    public function preferFirst()
    {
        $this->preference = Prefer::FIRST;

        return $this;
    }

}
