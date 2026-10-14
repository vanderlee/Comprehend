<?php

declare (strict_types=1);
namespace Vanderlee\Comprehend\Parser\Terminal;

use Vanderlee\Comprehend\Runtime\ParseSession;
/**
 * Classes implementing this can scan.
 *
 * @author Martijn
 */
trait CaseSensitiveTrait
{
    /**
     * @var bool
     */
    private $caseSensitivity = null;
    private function pushCaseSensitivityToContext(ParseSession $context)
    {
        if ($this->caseSensitivity !== null) {
            $context->pushCaseSensitivity($this->caseSensitivity);
        }
    }
    private function popCaseSensitivityFromContext(ParseSession $context)
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
}
