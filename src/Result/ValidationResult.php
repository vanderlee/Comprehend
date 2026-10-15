<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

/**
 * Carries semantic acceptance and its validation errors. Allows ParseResult to report
 * validation separately from syntactic success and recognition diagnostics.
 */
final class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(public readonly bool $valid, public readonly array $errors = []) {}
}
