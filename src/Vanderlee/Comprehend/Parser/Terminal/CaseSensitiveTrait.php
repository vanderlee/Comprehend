<?php

namespace vanderlee\comprehend\parser\terminal;

use vanderlee\comprehend\core\Context;

/**
 * Classes implementing this can scan
 *
 * @author Martijn
 */
trait CaseSensitiveTrait
{

    /**
     * @var boolean
     */
    private $caseSensitivity = null;

    private function pushCaseSensitivityToContext(Context $context)
    {
        if ($this->caseSensitivity !== null) {
            $context->pushCaseSensitivity($this->caseSensitivity);
        }
    }

    private function popCaseSensitivityFromContext(Context $context)
    {
        if ($this->caseSensitivity !== null) {
            $context->popCaseSensitivity();
        }
    }

    public function setCaseSensitivity(string $preference)
    {
        $this->caseSensitivity = $preference;

        return $this;
    }

    public function caseSensitive()
    {
        $this->caseSensitivity = true;

        return $this;
    }

    public function caseInsensitive()
    {
        $this->caseSensitivity = false;

        return $this;
    }

}
