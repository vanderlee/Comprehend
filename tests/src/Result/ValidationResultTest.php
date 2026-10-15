<?php

declare(strict_types=1);

namespace Tests\Src\Result;

use Error;
use PHPUnit\Framework\TestCase;
use Vanderlee\Comprehend\Result\ValidationResult;

uses(TestCase::class);

test('validation errors are an immutable snapshot', function (): void {
    $errors = ['invalid item'];
    $result = new ValidationResult(false, $errors);
    $errors[] = 'later error';

    $this->assertFalse($result->valid);
    $this->assertSame(['invalid item'], $result->errors);
    $this->assertSame([], (new ValidationResult(true))->errors);
    $this->expectException(Error::class);

    $result->errors[] = 'mutation';
});
