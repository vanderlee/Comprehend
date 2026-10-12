<?php

namespace Vanderlee\Comprehend\Core\Context;

use Vanderlee\Comprehend\Source\Utf8Source;

trait CaseSensitiveContextTrait
{
    private $caseSensitivity = [];

    public function pushCaseSensitivity($caseSensitive = true)
    {
        $this->caseSensitivity[] = (bool)$caseSensitive;
    }

    public function popCaseSensitivity()
    {
        return array_pop($this->caseSensitivity);
    }

    public function isCaseSensitive()
    {
        return end($this->caseSensitivity);
    }

    // Helper
    public function handleCase($text)
    {
        if ($this->isCaseSensitive()) {
            return $text;
        }
        if ($this->source() instanceof Utf8Source) {
            return mb_strtolower($text, 'UTF-8');
        }
        return $this->source() !== null ? strtolower($text) : mb_strtolower($text);
    }
}
