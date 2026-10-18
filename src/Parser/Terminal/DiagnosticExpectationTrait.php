<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser\Terminal;

/** Uses the parser's string description as its fallback diagnostic expectation. */
trait DiagnosticExpectationTrait
{
    abstract public function __toString(): string;

    protected function diagnosticExpectation(): string
    {
        return (string) $this;
    }
}
