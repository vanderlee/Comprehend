<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime\Scope;

use Vanderlee\Comprehend\Source\SourceMode;

trait CaseSensitiveContextTrait
{
    /**
     * @var list<bool>
     */
    private array $caseSensitivity = [];

    public function pushCaseSensitivity(bool $caseSensitive = true): void
    {
        $this->caseSensitivity[] = $caseSensitive;
    }

    public function popCaseSensitivity(): bool
    {
        $caseSensitive = array_pop($this->caseSensitivity);
        if ($caseSensitive === null) {
            throw new \LogicException('Case sensitivity scope is empty');
        }

        return $caseSensitive;
    }

    public function handleCase(string $text): string
    {
        if ($this->isCaseSensitive()) {
            return $text;
        }
        if ($this->source()->mode() === SourceMode::UTF8) {
            return mb_strtolower($text, 'UTF-8');
        }

        return strtolower($text);
    }

    // Helper

    public function isCaseSensitive(): bool
    {
        return end($this->caseSensitivity);
    }
}
