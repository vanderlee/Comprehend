<?php

declare(strict_types=1);

namespace Tests\Fixtures\Syntax;

final class OtherCapabilityProvider implements PrimaryCapability
{
    public function identity(): int
    {
        return -1;
    }
}
