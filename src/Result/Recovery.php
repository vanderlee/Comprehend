<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use InvalidArgumentException;
use Vanderlee\Comprehend\Source\Span;

/** An invalid source region retained after synchronization, including any consumed boundary. */
final class Recovery
{
    public function __construct(
        public readonly Span $span,
        public readonly Diagnostic $diagnostic,
    ) {
        if ($span->byteLength() === 0 || $span->source !== $diagnostic->source) {
            throw new InvalidArgumentException('Recovery requires a nonempty region and a same-source diagnostic');
        }
    }
}
