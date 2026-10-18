<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime\Scope;

use Vanderlee\Comprehend\Source\SourceMode;
use LogicException;

/**
 * Maintains ParseSession's nested case-sensitivity policies. Supplies case normalization to
 * terminals while allowing directives to restore the enclosing policy.
 */
trait CaseSensitiveContextTrait
{
    /**
     * @var list<bool>
     */
    private array $caseSensitivityStack = [];

    public function pushCaseSensitivity(bool $caseSensitive = true): void
    {
        $this->caseSensitivityStack[] = $caseSensitive;
    }

    public function popCaseSensitivity(): bool
    {
        $caseSensitive = array_pop($this->caseSensitivityStack);
        if ($caseSensitive === null) {
            throw new LogicException('Case sensitivity scope is empty');
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

    public function isCaseSensitive(): bool
    {
        return end($this->caseSensitivityStack);
    }
}
