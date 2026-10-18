<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Result;

use InvalidArgumentException;
use Vanderlee\Comprehend\Source\Span;

/** An invalid source region retained after synchronization, including any consumed boundary. */
final readonly class Recovery
{
    public function __construct(
        public Span $span,
        public Diagnostic $diagnostic,
    ) {
        if ($span->byteLength() === 0 || $span->source !== $diagnostic->source) {
            throw new InvalidArgumentException('Recovery requires a nonempty region and a same-source diagnostic');
        }
    }
}
