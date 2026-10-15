<?php

declare (strict_types=1);

namespace Vanderlee\Comprehend\Result;

/** Semantic acceptance is separate from syntactic success and diagnostics. */
final readonly class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(public bool $valid, public array $errors = []) {}
}
