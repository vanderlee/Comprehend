<?php

namespace Vanderlee\Comprehend\Core\Context;

trait CaseSensitiveContextTrait
{
    private $caseSensitivity = [];

    public function pushCaseSensitivity($caseSensitive = true)
    {
        array_push($this->caseSensitivity, (bool)$caseSensitive);
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
        return $this->isCaseSensitive()
            ? $text
            : mb_strtolower($text);
    }
}