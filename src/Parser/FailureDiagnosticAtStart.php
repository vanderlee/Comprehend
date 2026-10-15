<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Parser;

/**
 * Marks parsers whose fallback failure diagnostic uses the entry byte offset,
 * independently of observed failure progress. Explicitly recorded diagnostics are unchanged.
 */
interface FailureDiagnosticAtStart {}
