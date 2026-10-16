<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPStan\Testing\TypeInferenceTestCase;

abstract class CoreStructuralTypeInferenceTestCase extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/PHPStan/core-structures.neon'];
    }
}
