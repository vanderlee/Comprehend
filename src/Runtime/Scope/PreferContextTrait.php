<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime\Scope;

use Vanderlee\Comprehend\Runtime\ChoicePolicy;
use LogicException;

/**
 * Maintains ParseSession's stack of validated choice preferences. Lets Choice and Prefer share
 * first, longest and shortest selection policies across nested scopes.
 */
trait PreferContextTrait
{
    /**
     * @var list<string>
     */
    private array $preferenceStack = [];

    public function pushPreference(string $preference): void
    {
        // Invalid policies must leave the enclosing scope unchanged.
        ChoicePolicy::assertValid($preference);
        $this->preferenceStack[] = $preference;
    }

    public function popPreference(): void
    {
        array_pop($this->preferenceStack);
    }

    public function getPreference(): string
    {
        $preference = end($this->preferenceStack);
        if ($preference === false) {
            throw new LogicException('Choice preference scope is empty');
        }

        return $preference;
    }
}
