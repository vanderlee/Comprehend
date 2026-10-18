<?php

declare(strict_types=1);

namespace Tests\Fixtures\Syntax;

interface SecondaryCapability extends PrimaryCapability
{
    public function secondaryIdentity(): int;
}
