<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Source;

/**
 * Distinguishes arbitrary-byte recognition from validated UTF-8 code-point recognition. Sources
 * expose this mode so terminals interpret units consistently while offsets remain byte-based.
 */
enum SourceMode: string
{
    case BYTES = 'bytes';
    case UTF8 = 'utf8';
}
