<?php

namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Core\Context;

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

    /**
     * @param bool $preference
     *
     * @return $this
     */
    public function setCaseSensitivity($preference)
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
