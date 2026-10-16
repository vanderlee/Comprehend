<?php

declare(strict_types=1);

namespace Tests\Unit\src\PHPStan;

use Tests\Fixtures\ValueTypeInferenceTestCase;

require_once __DIR__ . '/../../../../docs/examples/application-values-grammar.php';

uses(ValueTypeInferenceTestCase::class);

// PHPStan and the test runner bundle different PhpParser versions; isolate the analyzer container.

test('value inference preserves mappings lists defaults and tutorial domain outputs', function (): void {
    $assertions = $this::gatherAssertTypes(__DIR__ . '/../../../Fixtures/PHPStan/values.php');
    $this->assertNotEmpty($assertions);
    foreach ($assertions as $assertion) {
        $this->assertFileAsserts(...$assertion);
    }
})->setRunTestInSeparateProcess(true)->setPreserveGlobalState(false);
