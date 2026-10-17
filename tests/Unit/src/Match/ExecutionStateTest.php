<?php

declare(strict_types=1);

namespace Tests\Src\Match;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vanderlee\Comprehend\Match\ExecutionState;
use Vanderlee\Comprehend\Match\Success;
use Vanderlee\Comprehend\Result\ValidationResult;

uses(TestCase::class);

test('empty execution state can be discarded but cached empty results and failures cannot', function () {
    $state = new ExecutionState();
    $this->assertTrue($state->isEmpty());
    $state->resultCache = [];
    $this->assertFalse($state->isEmpty());
    $state->resultCache = null;
    $state->validationError = new RuntimeException('retained');
    $this->assertFalse($state->isEmpty());
    $state->validationError = null;
    $state->validation = new ValidationResult();
    $this->assertFalse($state->isEmpty());
});

test('retained children callbacks and in-progress evaluation keep execution state live', function () {
    $state = new ExecutionState();
    $state->successes[] = new Success();
    $this->assertFalse($state->isEmpty());
    $state->successes = [];
    $state->customCallbacks[] = static fn() => null;
    $this->assertFalse($state->isEmpty());
    $state->customCallbacks = [];
    $state->evaluatingResults = true;
    $this->assertFalse($state->isEmpty());
});
