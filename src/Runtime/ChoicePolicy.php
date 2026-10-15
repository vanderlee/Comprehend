<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Runtime;

use DomainException;

/** Shared choice ranking values and validation for authoring and recognition scopes. */
final class ChoicePolicy
{
    public const FIRST = 'first';
    public const LONGEST = 'longest';
    public const SHORTEST = 'shortest';

    public static function assertValid(string $policy): void
    {
        if (!in_array($policy, [self::FIRST, self::LONGEST, self::SHORTEST], true)) {
            throw new DomainException('Invalid preference `' . $policy . '`');
        }
    }
}
