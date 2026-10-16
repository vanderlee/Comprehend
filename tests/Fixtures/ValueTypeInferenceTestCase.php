<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPStan\Testing\TypeInferenceTestCase;

abstract class ValueTypeInferenceTestCase extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../phpstan.neon'];
    }
}
