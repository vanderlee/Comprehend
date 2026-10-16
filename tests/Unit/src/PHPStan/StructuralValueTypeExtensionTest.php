<?php

declare(strict_types=1);

namespace Tests\Unit\src\PHPStan;

use Tests\Fixtures\CoreStructuralTypeInferenceTestCase;

uses(CoreStructuralTypeInferenceTestCase::class);

test('core structural value inference works without extraction support', function (): void {
    $assertions = $this::gatherAssertTypes(__DIR__ . '/../../../Fixtures/PHPStan/core-structures.php');
    $this->assertNotEmpty($assertions);
    foreach ($assertions as $assertion) {
        $this->assertFileAsserts(...$assertion);
    }
})->setRunTestInSeparateProcess(true)->setPreserveGlobalState(false);
